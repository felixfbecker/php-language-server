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
    CompletionItemKind
};
use function LanguageServer\{strStartsWith};
use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;

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
     * @return CompletionList
     */
    public function provideCompletion(PhpDocument $doc, Position $pos): CompletionList
    {
        // This can be made much more performant if the tree follows specific invariants.
        $node = $doc->getNodeAtPosition($pos);

        $offset = $node === null ? -1 : $pos->toOffset($node->getFileContents());
        if ($node !== null && $offset > $node->getEndPosition() &&
            $node->parent->getLastChild() instanceof PhpParser\MissingToken
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

        if ($node === null || $node instanceof Node\Statement\InlineHtml || $pos == new Position(0, 0)) {
            $item = new CompletionItem('<?php', CompletionItemKind::KEYWORD);
            $item->textEdit = new TextEdit(
                new Range($pos, $pos),
                stripStringOverlap($doc->getRange(new Range(new Position(0, 0), $pos)), '<?php')
            );
            $list->items[] = $item;
        } /*

        VARIABLES */
        elseif (
            $node instanceof Node\Expression\Variable &&
            !(
                $node->parent instanceof Node\Expression\ScopedPropertyAccessExpression &&
                $node->parent->memberName === $node)
        ) {
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
        } /*

        MEMBER ACCESS EXPRESSIONS
           $a->c#
           $a-># */
        elseif ($node instanceof Node\Expression\MemberAccessExpression) {
            $prefixes = FqnUtilities\getFqnsFromType(
                $this->definitionResolver->resolveExpressionNodeToType($node->dereferencableExpression)
            );
            $prefixes = $this->expandParentFqns($prefixes);

            foreach ($prefixes as &$prefix) {
                $prefix .= '->';
            }

            unset($prefix);

            foreach ($this->index->getDefinitions() as $fqn => $def) {
                foreach ($prefixes as $prefix) {
                    if (substr($fqn, 0, strlen($prefix)) === $prefix && !$def->isGlobal) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }
        } /*

        SCOPED PROPERTY ACCESS EXPRESSIONS
            A\B\C::$a#
            A\B\C::#
            A\B\C::$#
            A\B\C::foo#
            TODO: $a::# */
        elseif (
            ($scoped = $node->parent) instanceof Node\Expression\ScopedPropertyAccessExpression ||
            ($scoped = $node) instanceof Node\Expression\ScopedPropertyAccessExpression
        ) {
            $prefixes = FqnUtilities\getFqnsFromType(
                $classType = $this->definitionResolver->resolveExpressionNodeToType($scoped->scopeResolutionQualifier)
            );

            $prefixes = $this->expandParentFqns($prefixes);

            foreach ($prefixes as &$prefix) {
                $prefix .= '::';
            }

            unset($prefix);

            foreach ($this->index->getDefinitions() as $fqn => $def) {
                foreach ($prefixes as $prefix) {
                    if (substr(strtolower($fqn), 0, strlen($prefix)) === strtolower($prefix) && !$def->isGlobal) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }
        } elseif (ParserHelpers::isConstantFetch($node) ||
            ($creation = $node->parent) instanceof Node\Expression\ObjectCreationExpression ||
            (($creation = $node) instanceof Node\Expression\ObjectCreationExpression)) {
            $class = isset($creation) ? $creation->classTypeDesignator : $node;

            $prefix = $class instanceof Node\QualifiedName
                ? (string)PhpParser\ResolvedName::buildName($class->nameParts, $class->getFileContents())
                : $class->getText($node->getFileContents());

            $namespaceDefinition = $node->getNamespaceDefinition();

            list($namespaceImportTable,,) = $node->getImportTablesForCurrentScope();
            foreach ($namespaceImportTable as $alias => $name) {
                $namespaceImportTable[$alias] = (string)$name;
            }

            foreach ($this->index->getDefinitions() as $fqn => $def) {
                $fqnStartsWithPrefix = strStartsWith($fqn, $prefix);
                $fqnContainsPrefix = empty($prefix) || strpos($fqn, $prefix) !== false;
                if (($def->canBeInstantiated || ($def->isGlobal && !isset($creation))) && $fqnContainsPrefix) {
                    if ($namespaceDefinition !== null && $namespaceDefinition->name !== null) {
                        $namespacePrefix = (string)PhpParser\ResolvedName::buildName($namespaceDefinition->name->nameParts, $node->getFileContents());

                        $isAliased = false;

                        $isNotFullyQualified = !($class instanceof Node\QualifiedName) || !$class->isFullyQualifiedName();
                        if ($isNotFullyQualified) {
                            foreach ($namespaceImportTable as $alias => $name) {
                                if (strStartsWith($fqn, $name)) {
                                    $fqn = $alias;
                                    $isAliased = true;
                                    break;
                                }
                            }
                        }


                        if (!$isNotFullyQualified && ($fqnStartsWithPrefix || strStartsWith($fqn, $namespacePrefix . "\\" . $prefix))) {
                            // $fqn = $fqn;
                        } elseif (!$isAliased && !array_search($fqn, array_values($namespaceImportTable))) {
                            if (empty($prefix)) {
                                $fqn = '\\' . $fqn;
                            } elseif (strStartsWith($fqn, $namespacePrefix . "\\" . $prefix)) {
                                $fqn = substr($fqn, strlen($namespacePrefix) + 1);
                            } else {
                                continue;
                            }
                        } elseif (!$isAliased) {
                            continue;
                        }
                    } elseif ($fqnStartsWithPrefix && $class instanceof Node\QualifiedName && $class->isFullyQualifiedName()) {
                        $fqn = '\\' . $fqn;
                    }

                    $item = CompletionItem::fromDefinition($def);

                    $item->insertText = $fqn;
                    $list->items[] = $item;
                }
            }

            if (!isset($creation)) {
                foreach (self::KEYWORDS as $keyword) {
                    $item = new CompletionItem($keyword, CompletionItemKind::KEYWORD);
                    $item->insertText = $keyword . ' ';
                    $list->items[] = $item;
                }
            }
        } elseif (ParserHelpers::isConstantFetch($node)) {
            $prefix = (string) ($node->getResolvedName() ?? PhpParser\ResolvedName::buildName($node->nameParts, $node->getFileContents()));
            foreach (self::KEYWORDS as $keyword) {
                $item = new CompletionItem($keyword, CompletionItemKind::KEYWORD);
                $item->insertText = $keyword . ' ';
                $list->items[] = $item;
            }
        }

        return $list;
    }

    /**
     * Adds the FQNs of all parent classes to an array of FQNs of classes
     *
     * @param string[] $fqns
     * @return string[]
     */
    private function expandParentFqns(array $fqns): array
    {
        $expanded = $fqns;
        foreach ($fqns as $fqn) {
            $def = $this->index->getDefinition($fqn);
            if ($def) {
                foreach ($this->expandParentFqns($def->extends ?? []) as $parent) {
                    $expanded[] = $parent;
                }
            }
        }
        return $expanded;
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
        while ($level && !ParserHelpers::isFunctionLike($level)) {
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
        if ($level && ParserHelpers::isFunctionLike($level) && $level->parameters !== null) {
            foreach ($level->parameters->getValues() as $param) {
                $paramName = $param->getName();
                if (empty($namePrefix) || strpos($paramName, $namePrefix) !== false) {
                    $vars[$paramName] = $param;
                }
            }

            if ($level instanceof Node\Expression\AnonymousFunctionCreationExpression && $level->anonymousFunctionUseClause !== null &&
                $level->anonymousFunctionUseClause->useVariableNameList !== null) {
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

    private function isAssignmentToVariableWithPrefix($node, $namePrefix)
    {
        return $node instanceof Node\Expression\AssignmentExpression
            && $node->leftOperand instanceof Node\Expression\Variable
            && (empty($namePrefix) || strpos($node->leftOperand->getName(), $namePrefix) !== false);
    }
}
