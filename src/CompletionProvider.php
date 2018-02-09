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
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\ResolvedName;
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
    public function provideCompletion(
        PhpDocument $doc,
        Position $pos,
        CompletionContext $context = null
    ): CompletionList {
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
                    $context === null
                    // Make sure to not suggest on the > trigger character in HTML
                    || $context->triggerKind === CompletionTriggerKind::INVOKED
                    || $context->triggerCharacter === '<'
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

            // Find variables, parameters and use statements in the scope
            $namePrefix = $node->getName() ?? '';
            foreach ($this->suggestVariablesAtNode($node, $namePrefix) as $var) {
                $item = new CompletionItem;
                $item->kind = CompletionItemKind::VARIABLE;
                $item->label = '$' . $var->getName();
                $item->documentation = $this->definitionResolver->getDocumentationFromNode($var);
                $item->detail = (string)$this->definitionResolver->getTypeFromNode($var);
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

            // The FQNs of the symbol and its parents (eg the implemented interfaces)
            foreach ($this->expandParentFqns($fqns) as $parentFqn) {
                // Add the object access operator to only get members of all parents
                $prefix = $parentFqn . '->';
                $prefixLen = strlen($prefix);
                // Collect fqn definitions
                foreach ($this->index->getChildDefinitionsForFqn($parentFqn) as $fqn => $def) {
                    if (substr($fqn, 0, $prefixLen) === $prefix && $def->isMember) {
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

            // The FQNs of the symbol and its parents (eg the implemented interfaces)
            foreach ($this->expandParentFqns($fqns) as $parentFqn) {
                // Append :: operator to only get static members of all parents
                $prefix = strtolower($parentFqn . '::');
                $prefixLen = strlen($prefix);
                // Collect fqn definitions
                foreach ($this->index->getChildDefinitionsForFqn($parentFqn) as $fqn => $def) {
                    if (substr(strtolower($fqn), 0, $prefixLen) === $prefix && $def->isMember) {
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

            $filterNameTokens = static function ($tokens) {
                return array_values(
                    array_filter(
                        $tokens,
                        static function ($token): bool {
                            return $token->kind === PhpParser\TokenKind::Name;
                        }
                    )
                );
            };

            /** @var string[] The written name, exploded by \ */
            $prefix = array_map(
                static function ($part) use ($node) : string {
                    return $part->getText($node->getFileContents());
                },
                $filterNameTokens(
                    $nameNode instanceof Node\QualifiedName
                    ? $nameNode->nameParts
                    : [$nameNode]
                )
            );

            if ($prefix === ['']) {
                $prefix = [];
            }

            /** Whether the prefix is qualified (contains at least one backslash) */
            $isQualified = $nameNode instanceof Node\QualifiedName && $nameNode->isQualifiedName();

            /** Whether the prefix is fully qualified (begins with a backslash) */
            $isFullyQualified = $nameNode instanceof Node\QualifiedName && $nameNode->isFullyQualifiedName();

            /** The closest NamespaceDefinition Node */
            $namespaceNode = $node->getNamespaceDefinition();

            // Get the namespace use statements
            // TODO: use function statements, use const statements

            /** @var string[] $aliases A map from local alias to fully qualified name */
            list($aliases,,) = $node->getImportTablesForCurrentScope();

            /** @var array Array of [fqn=string, requiresRoaming=bool] the prefix may represent. */
            $possibleFqns = [];

            if ($isFullyQualified) {
                // Case \Microsoft\PhpParser\Res|
                $possibleFqns[] = [$prefix, false];
            } else if ($fqnAfterAlias = $this->tryApplyAlias($aliases, $prefix)) {
                // Cases handled here: (i.e. all namespaces involving use clauses)
                //
                //   use Microsoft\PhpParser\Node; //Note that Node is both a class and a namespace.
                //   Nod|
                //   Node\Qual|
                //
                //   use Microsoft\PhpParser as TheParser;
                //   TheParser\Nod|
                $possibleFqns[] = [$fqnAfterAlias, false];
            } else if ($namespaceNode) {
                // Cases handled here:
                //
                //    namespace Foo;
                //    Microsoft\PhpParser\Nod| // Can refer only to \Foo\Microsoft, not to \Microsoft.
                //
                //    namespace Foo;
                //    Test| // Can refer either to functions or constants at the global scope, or to
                //          // everything below \Foo. (Global fallback / roaming)
                /** @var \Microsoft\PhpParser\ResolvedName Declared namespace of the file (or section) */
                $namespacedFqn = array_merge(
                    array_map(
                        static function ($token) use ($namespaceNode): string {
                            return $token->getText($namespaceNode->getFileContents());
                        },
                        $filterNameTokens($namespaceNode->name->nameParts)
                    ),
                    $prefix
                );
                $possibleFqns[] = [$namespacedFqn, false];
                if (!$isQualified) {
                    // Case of global fallback. If nothing is entered, also complete for root-level classnames.
                    // If something has been entered, complete root-level roamed symbols only.
                    $possibleFqns[] = [$prefix, !empty($prefix)];
                }
            } else {
                // Case handled here: (no namespace declaration in file)
                //
                // Microsoft\PhpParser\N|
                $possibleFqns[] = [$prefix, false];
            }

            $prefixStr = implode('\\', $prefix);
            /** @var int Length of $prefix */
            $prefixLen = strlen($prefixStr);

            // If there is a prefix that does not contain a slash, suggest used names.
            if (!$isQualified) {
                foreach ($aliases as $alias => $fqn) {
                    // Suggest symbols that have been `use`d and match the prefix
                    if (substr($alias, 0, $prefixLen) === $prefixStr
                        && ($def = $this->index->getDefinition((string)$fqn))) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }

            foreach ($possibleFqns as list ($fqnToSearch, $requiresRoaming)) {
                $namespaceToSearch = $fqnToSearch;
                array_pop($namespaceToSearch);
                $namespaceToSearch = implode('\\', $namespaceToSearch);
                $fqnToSearch = implode('\\', $fqnToSearch);
                $fqnToSearchLen = strlen($fqnToSearch);
                foreach ($this->index->getChildDefinitionsForFqn($namespaceToSearch) as $fqn => $def) {
                    if (isset($creation) && !$def->canBeInstantiated) {
                        // Only suggest classes for `new`
                        continue;
                    }
                    if ($requiresRoaming && !$def->roamed) {
                        continue;
                    }

                    if (substr($fqn, 0, $fqnToSearchLen) === $fqnToSearch) {
                        $item = CompletionItem::fromDefinition($def);
                        if (($aliasMatch = $this->tryMatchAlias($aliases, $fqn)) !== null) {
                            $item->insertText = $aliasMatch;
                        } else if ($namespaceNode && (empty($prefix) || $requiresRoaming)) {
                            // Insert the global FQN with a leading backslash.
                            // For empty prefix: Assume that the user wants an FQN. They have not
                            // started writing anything yet, so we are not second-guessing.
                            // For roaming: Second-guess that the user doesn't want to depend on
                            // roaming.
                            $item->insertText = '\\' . $fqn;
                        } else {
                            // Insert the FQN without a leading backslash
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
            }

            // Suggest keywords
            if (!$isQualified && !isset($creation)) {
                foreach (self::KEYWORDS as $keyword) {
                    if (substr($keyword, 0, $prefixLen) === $prefixStr) {
                        $item = new CompletionItem($keyword, CompletionItemKind::KEYWORD);
                        $item->insertText = $keyword;
                        $list->items[] = $item;
                    }
                }
            }
        }

        return $list;
    }

    private function tryMatchAlias(
        array $aliases,
        string $fullyQualifiedName
    ): ?string {
        $fullyQualifiedName = explode('\\', $fullyQualifiedName);
        $aliasMatch = null;
        $aliasMatchLength = null;
        foreach ($aliases as $alias => $aliasFqn) {
            $aliasFqn = $aliasFqn->getNameParts();
            $aliasFqnLength = count($aliasFqn);
            if ($aliasMatchLength && $aliasFqnLength < $aliasFqnLength) {
                // Find the longest possible match. This one won't do.
                continue;
            }
            $fqnStart = array_slice($fullyQualifiedName, 0, $aliasFqnLength);
            if ($fqnStart === $aliasFqn) {
                $aliasMatch = $alias;
                $aliasMatchLength = $aliasFqnLength;
            }
        }

        if ($aliasMatch === null) {
            return null;
        }

        $fqnNoAlias = array_slice($fullyQualifiedName, $aliasMatchLength);
        return join('\\', array_merge([$aliasMatch], $fqnNoAlias));
    }

    /**
     * Tries to convert a partially qualified name to an FQN using aliases.
     *
     * Example:
     *
     * use Microsoft\PhpParser as TheParser;
     * "TheParser\Node" will convert to "Microsoft\PhpParser\Node"
     *
     * @param \Microsoft\PhpParser\ResolvedName[] $aliases
     *   Aliases available in the scope of resolution. Keyed by alias.
     * @param string[] $partiallyQualifiedName
     **/
    private function tryApplyAlias(
        array $aliases,
        array $partiallyQualifiedName
    ): ?array {
        if (empty($partiallyQualifiedName)) {
            return null;
        }
        $head = $partiallyQualifiedName[0];
        $tail = array_slice($partiallyQualifiedName, 1);
        if (!isset($aliases[$head])) {
            return null;
        }
        return array_merge($aliases[$head]->getNameParts(), $tail);
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

    /**
     * Will walk the AST upwards until a function-like node is met
     * and at each level walk all previous siblings and their children to search for definitions
     * of that variable
     *
     * @param Node $node
     * @param string $namePrefix Prefix to filter
     * @return array <Node\Expr\Variable|Node\Param|Node\Expr\ClosureUse>
     */
    private function suggestVariablesAtNode(Node $node, string $namePrefix = ''): array
    {
        $vars = [];

        // Find variables in the node itself
        // When getting completion in the middle of a function, $node will be the function node
        // so we need to search it
        foreach ($this->findVariableDefinitionsInNode($node, $namePrefix) as $var) {
            // Only use the first definition
            if (!isset($vars[$var->name])) {
                $vars[$var->name] = $var;
            }
        }

        // Walk the AST upwards until a scope boundary is met
        $level = $node;
        while ($level && !($level instanceof PhpParser\FunctionLike)) {
            // Walk siblings before the node
            $sibling = $level;
            while ($sibling = $sibling->getPreviousSibling()) {
                // Collect all variables inside the sibling node
                foreach ($this->findVariableDefinitionsInNode($sibling, $namePrefix) as $var) {
                    $vars[$var->getName()] = $var;
                }
            }
            $level = $level->parent;
        }

        // If the traversal ended because a function was met,
        // also add its parameters and closure uses to the result list
        if ($level && $level instanceof PhpParser\FunctionLike && $level->parameters !== null) {
            foreach ($level->parameters->getValues() as $param) {
                $paramName = $param->getName();
                if (empty($namePrefix) || strpos($paramName, $namePrefix) !== false) {
                    $vars[$paramName] = $param;
                }
            }

            if ($level instanceof Node\Expression\AnonymousFunctionCreationExpression
                && $level->anonymousFunctionUseClause !== null
                && $level->anonymousFunctionUseClause->useVariableNameList !== null) {
                foreach ($level->anonymousFunctionUseClause->useVariableNameList->getValues() as $use) {
                    $useName = $use->getName();
                    if (empty($namePrefix) || strpos($useName, $namePrefix) !== false) {
                        $vars[$useName] = $use;
                    }
                }
            }
        }

        return array_values($vars);
    }

    /**
     * Searches the subnodes of a node for variable assignments
     *
     * @param Node $node
     * @param string $namePrefix Prefix to filter
     * @return Node\Expression\Variable[]
     */
    private function findVariableDefinitionsInNode(Node $node, string $namePrefix = ''): array
    {
        $vars = [];
        // If the child node is a variable assignment, save it

        $isAssignmentToVariable = function ($node) {
            return $node instanceof Node\Expression\AssignmentExpression;
        };

        if ($this->isAssignmentToVariableWithPrefix($node, $namePrefix)) {
            $vars[] = $node->leftOperand;
        } elseif ($node instanceof Node\ForeachKey || $node instanceof Node\ForeachValue) {
            foreach ($node->getDescendantNodes() as $descendantNode) {
                if ($descendantNode instanceof Node\Expression\Variable
                    && ($namePrefix === '' || strpos($descendantNode->getName(), $namePrefix) !== false)
                ) {
                    $vars[] = $descendantNode;
                }
            }
        } else {
            // Get all descendent variables, then filter to ones that start with $namePrefix.
            // Avoiding closure usage in tight loop
            foreach ($node->getDescendantNodes($isAssignmentToVariable) as $descendantNode) {
                if ($this->isAssignmentToVariableWithPrefix($descendantNode, $namePrefix)) {
                    $vars[] = $descendantNode->leftOperand;
                }
            }
        }

        return $vars;
    }

    private function isAssignmentToVariableWithPrefix(Node $node, string $namePrefix): bool
    {
        return $node instanceof Node\Expression\AssignmentExpression
            && $node->leftOperand instanceof Node\Expression\Variable
            && ($namePrefix === '' || strpos($node->leftOperand->getName(), $namePrefix) !== false);
    }
}
