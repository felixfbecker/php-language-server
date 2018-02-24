<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Index\ReadableIndex;
use LanguageServer\Protocol\{
    TextEdit,
    Range,
    Position,
    CompletionList,
    CompletionItem,
    CompletionItemKind,
    CompletionContext,
    CompletionTriggerKind
};
use function LanguageServer\Scope\getScopeAtNode;
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;
use Generator;

class CompletionProvider
{
    const KEYWORDS = [
        '?>',
        '__halt_compiler',
        'abstract',
        'and',
        'array',
        'as',
        'break',
        'callable',
        'case',
        'catch',
        'class',
        'clone',
        'const',
        'continue',
        'declare',
        'default',
        'die',
        'do',
        'echo',
        'else',
        'elseif',
        'empty',
        'enddeclare',
        'endfor',
        'endforeach',
        'endif',
        'endswitch',
        'endwhile',
        'eval',
        'exit',
        'extends',
        'false',
        'final',
        'finally',
        'for',
        'foreach',
        'function',
        'global',
        'goto',
        'if',
        'implements',
        'include',
        'include_once',
        'instanceof',
        'insteadof',
        'interface',
        'isset',
        'list',
        'namespace',
        'new',
        'null',
        'or',
        'print',
        'private',
        'protected',
        'public',
        'require',
        'require_once',
        'return',
        'static',
        'switch',
        'throw',
        'trait',
        'true',
        'try',
        'unset',
        'use',
        'var',
        'while',
        'xor',
        'yield'
    ];

    /**
     * @var DefinitionResolver
     */
    private $definitionResolver;

    /**
     * @var Project
     */
    private $project;

    /**
     * @var ReadableIndex
     */
    private $index;

    /**
     * @param DefinitionResolver $definitionResolver
     * @param ReadableIndex $index
     */
    public function __construct(DefinitionResolver $definitionResolver, ReadableIndex $index)
    {
        $this->definitionResolver = $definitionResolver;
        $this->index = $index;
    }

    /**
     * Returns suggestions for a specific cursor position in a document
     *
     * @param PhpDocument $doc The opened document
     * @param Position $pos The cursor position
     * @param CompletionContext $context The completion context
     * @return CompletionList
     */
    public function provideCompletion(PhpDocument $doc, Position $pos, CompletionContext $context = null): CompletionList
    {
        // This can be made much more performant if the tree follows specific invariants.
        $node = $doc->getNodeAtPosition($pos);

        // Get the node at the position under the cursor
        $offset = $node === null ? -1 : $pos->toOffset($node->getFileContents());
        if (
            $node !== null
            && $offset > $node->getEndPosition()
            && $node->parent !== null
            && $node->parent->getLastChild() instanceof PhpParser\MissingToken
        ) {
            $node = $node->parent;
        }

        $list = new CompletionList;
        $list->isIncomplete = true;

        if ($node instanceof Node\Expression\Variable &&
            $node->parent instanceof Node\Expression\ObjectCreationExpression &&
            $node->name instanceof PhpParser\MissingToken
        ) {
            $node = $node->parent;
        }

        // Inspect the type of expression under the cursor

        $content = $doc->getContent();
        $offset = $pos->toOffset($content);
        if (
            $node === null
            || (
                $node instanceof Node\Statement\InlineHtml
                && (
                    $context !== null
                    // Make sure to not suggest on the > trigger character in HTML
                    && (
                        $context->triggerKind === CompletionTriggerKind::INVOKED
                        || $context->triggerCharacter === '<'
                    )
                )
            )
            || $pos == new Position(0, 0)
        ) {
            // HTML, beginning of file

            // Inside HTML and at the beginning of the file, propose <?php
            $item = new CompletionItem('<?php', CompletionItemKind::KEYWORD);
            $item->textEdit = new TextEdit(
                new Range($pos, $pos),
                stripStringOverlap($doc->getRange(new Range(new Position(0, 0), $pos)), '<?php')
            );
            $list->items[] = $item;

        } elseif (
            $node instanceof Node\Expression\Variable
            && !(
                $node->parent instanceof Node\Expression\ScopedPropertyAccessExpression
                && $node->parent->memberName === $node
            )
        ) {
            // Variables
            //
            //    $|
            //    $a|
            //
            // TODO: Superglobals

            $namePrefix = $node->getName() ?? '';
            $prefixLen = strlen($namePrefix);
            $scope = getScopeAtNode($this->definitionResolver, $node);
            $variables = $scope->variables;
            if ($scope->thisVariable !== null) {
                $variables['this'] = $scope->thisVariable;
            }
            foreach ($variables as $name => $var) {
                if (substr($name, 0, $prefixLen) !== $namePrefix) {
                    continue;
                }
                $item = new CompletionItem;
                $item->kind = CompletionItemKind::VARIABLE;
                $item->label = '$' . $name;
                $item->documentation = $this->definitionResolver->getDocumentationFromNode($var->definitionNode);
                $item->detail = (string)$var->type;
                $item->textEdit = new TextEdit(
                    new Range($pos, $pos),
                    stripStringOverlap($doc->getRange(new Range(new Position(0, 0), $pos)), $item->label)
                );
                $list->items[] = $item;
            }

        } elseif ($node instanceof Node\Expression\MemberAccessExpression) {
            // Member access expressions
            //
            //    $a->c|
            //    $a->|

            // Multiple prefixes for all possible types
            $fqns = FqnUtilities\getFqnsFromType(
                $this->definitionResolver->resolveExpressionNodeToType($node->dereferencableExpression)
            );

            // Add the object access operator to only get members of all parents
            $prefixes = [];
            foreach ($this->expandParentFqns($fqns) as $prefix) {
                $prefixes[] = $prefix . '->';
            }

            // Collect all definitions that match any of the prefixes
            foreach ($this->index->getDefinitions() as $fqn => $def) {
                foreach ($prefixes as $prefix) {
                    if (substr($fqn, 0, strlen($prefix)) === $prefix && $def->isMember) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }

        } elseif (
            ($scoped = $node->parent) instanceof Node\Expression\ScopedPropertyAccessExpression ||
            ($scoped = $node) instanceof Node\Expression\ScopedPropertyAccessExpression
        ) {
            // Static class members and constants
            //
            //     A\B\C::$a|
            //     A\B\C::|
            //     A\B\C::$|
            //     A\B\C::foo|
            //
            //     TODO: $a::|

            // Resolve all possible types to FQNs
            $fqns = FqnUtilities\getFqnsFromType(
                $classType = $this->definitionResolver->resolveExpressionNodeToType($scoped->scopeResolutionQualifier)
            );

            // Append :: operator to only get static members of all parents
            $prefixes = [];
            foreach ($this->expandParentFqns($fqns) as $prefix) {
                $prefixes[] = $prefix . '::';
            }

            // Collect all definitions that match any of the prefixes
            foreach ($this->index->getDefinitions() as $fqn => $def) {
                foreach ($prefixes as $prefix) {
                    if (substr(strtolower($fqn), 0, strlen($prefix)) === strtolower($prefix) && $def->isMember) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }

        } elseif (
            ParserHelpers\isConstantFetch($node)
            // Creation gets set in case of an instantiation (`new` expression)
            || ($creation = $node->parent) instanceof Node\Expression\ObjectCreationExpression
            || (($creation = $node) instanceof Node\Expression\ObjectCreationExpression)
        ) {
            // Class instantiations, function calls, constant fetches, class names
            //
            //    new MyCl|
            //    my_func|
            //    MY_CONS|
            //    MyCla|

            // The name Node under the cursor
            $nameNode = isset($creation) ? $creation->classTypeDesignator : $node;

            /** The typed name */
            $prefix = $nameNode instanceof Node\QualifiedName
                ? (string)PhpParser\ResolvedName::buildName($nameNode->nameParts, $nameNode->getFileContents())
                : $nameNode->getText($node->getFileContents());
            $prefixLen = strlen($prefix);

            /** Whether the prefix is qualified (contains at least one backslash) */
            $isQualified = $nameNode instanceof Node\QualifiedName && $nameNode->isQualifiedName();

            /** Whether the prefix is fully qualified (begins with a backslash) */
            $isFullyQualified = $nameNode instanceof Node\QualifiedName && $nameNode->isFullyQualifiedName();

            /** The closest NamespaceDefinition Node */
            $namespaceNode = $node->getNamespaceDefinition();

            /** @var string The name of the namespace */
            $namespacedPrefix = null;
            if ($namespaceNode) {
                $namespacedPrefix = (string)PhpParser\ResolvedName::buildName($namespaceNode->name->nameParts, $node->getFileContents()) . '\\' . $prefix;
                $namespacedPrefixLen = strlen($namespacedPrefix);
            }

            // Get the namespace use statements
            // TODO: use function statements, use const statements

            /** @var string[] $aliases A map from local alias to fully qualified name */
            list($aliases,,) = $node->getImportTablesForCurrentScope();

            foreach ($aliases as $alias => $name) {
                $aliases[$alias] = (string)$name;
            }

            // If there is a prefix that does not start with a slash, suggest `use`d symbols
            if ($prefix && !$isFullyQualified) {
                foreach ($aliases as $alias => $fqn) {
                    // Suggest symbols that have been `use`d and match the prefix
                    if (substr($alias, 0, $prefixLen) === $prefix && ($def = $this->index->getDefinition($fqn))) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }

            // Suggest global symbols that either
            //  - start with the current namespace + prefix, if the Name node is not fully qualified
            //  - start with just the prefix, if the Name node is fully qualified
            foreach ($this->index->getDefinitions() as $fqn => $def) {

                $fqnStartsWithPrefix = substr($fqn, 0, $prefixLen) === $prefix;

                if (
                    // Exclude methods, properties etc.
                    !$def->isMember
                    && (
                        !$prefix
                        || (
                            // Either not qualified, but a matching prefix with global fallback
                            ($def->roamed && !$isQualified && $fqnStartsWithPrefix)
                            // Or not in a namespace or a fully qualified name or AND matching the prefix
                            || ((!$namespaceNode || $isFullyQualified) && $fqnStartsWithPrefix)
                            // Or in a namespace, not fully qualified and matching the prefix + current namespace
                            || (
                                $namespaceNode
                                && !$isFullyQualified
                                && substr($fqn, 0, $namespacedPrefixLen) === $namespacedPrefix
                            )
                        )
                    )
                    // Only suggest classes for `new`
                    && (!isset($creation) || $def->canBeInstantiated)
                ) {
                    $item = CompletionItem::fromDefinition($def);
                    // Find the shortest name to reference the symbol
                    if ($namespaceNode && ($alias = array_search($fqn, $aliases, true)) !== false) {
                        // $alias is the name under which this definition is aliased in the current namespace
                        $item->insertText = $alias;
                    } else if ($namespaceNode && !($prefix && $isFullyQualified)) {
                        // Insert the global FQN with leading backslash
                        $item->insertText = '\\' . $fqn;
                    } else {
                        // Insert the FQN without leading backlash
                        $item->insertText = $fqn;
                    }
                    // Don't insert the parenthesis for functions
                    // TODO return a snippet and put the cursor inside
                    if (substr($item->insertText, -2) === '()') {
                        $item->insertText = substr($item->insertText, 0, -2);
                    }
                    $list->items[] = $item;
                }
            }

            // If not a class instantiation, also suggest keywords
            if (!isset($creation)) {
                foreach (self::KEYWORDS as $keyword) {
                    if (substr($keyword, 0, $prefixLen) === $prefix) {
                        $item = new CompletionItem($keyword, CompletionItemKind::KEYWORD);
                        $item->insertText = $keyword;
                        $list->items[] = $item;
                    }
                }
            }
        }

        return $list;
    }

    /**
     * Yields FQNs from an array along with the FQNs of all parent classes
     *
     * @param string[] $fqns
     * @return Generator
     */
    private function expandParentFqns(array $fqns) : Generator
    {
        foreach ($fqns as $fqn) {
            yield $fqn;
            $def = $this->index->getDefinition($fqn);
            if ($def !== null) {
                foreach ($def->getAncestorDefinitions($this->index) as $name => $def) {
                    yield $name;
                }
            }
        }
    }
}
