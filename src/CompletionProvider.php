<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\Node;
use LanguageServer\Index\ReadableIndex;
use LanguageServer\Protocol\{
    TextEdit,
    Range,
    Position,
    CompletionList,
    CompletionItem,
    CompletionItemKind
};

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
     * @param ReadableIndex      $index
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
        $node = $doc->getNodeAtPosition($pos);

        if ($node instanceof Node\Expr\Error) {
            $node = $node->getAttribute('parentNode');
        }

        $list = new CompletionList;
        $list->isIncomplete = true;

        // A non-free node means we do NOT suggest global symbols
        if (
            $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\PropertyFetch
            || $node instanceof Node\Expr\StaticCall
            || $node instanceof Node\Expr\StaticPropertyFetch
            || $node instanceof Node\Expr\ClassConstFetch
        ) {
            // If the name is an Error node, just filter by the class
            if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\PropertyFetch) {
                // For instances, resolve the variable type
                $prefixes = DefinitionResolver::getFqnsFromType(
                    $this->definitionResolver->resolveExpressionNodeToType($node->var)
                );
            } else {
                // Static member reference
                $prefixes = [$node->class instanceof Node\Name ? (string)$node->class : ''];
            }
            $prefixes = $this->expandParentFqns($prefixes);
            // If we are just filtering by the class, add the appropiate operator to the prefix
            // to filter the type of symbol
            foreach ($prefixes as &$prefix) {
                if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\PropertyFetch) {
                    $prefix .= '->';
                } else if ($node instanceof Node\Expr\StaticCall || $node instanceof Node\Expr\ClassConstFetch) {
                    $prefix .= '::';
                } else if ($node instanceof Node\Expr\StaticPropertyFetch) {
                    $prefix .= '::$';
                }
            }
            unset($prefix);

            foreach ($this->index->getDefinitions() as $fqn => $def) {
                foreach ($prefixes as $prefix) {
                    if (substr($fqn, 0, strlen($prefix)) === $prefix && !$def->isGlobal) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }
        } else if (
            // A ConstFetch means any static reference, like a class, interface, etc. or keyword
            ($node instanceof Node\Name && $node->getAttribute('parentNode') instanceof Node\Expr\ConstFetch)
            || $node instanceof Node\Expr\New_
        ) {
            $prefix = '';
            $prefixLen = 0;
            if ($node instanceof Node\Name) {
                $isFullyQualified = $node->isFullyQualified();
                $prefix = (string)$node;
                $prefixLen = strlen($prefix);
                $namespacedPrefix = (string)$node->getAttribute('namespacedName');
                $namespacedPrefixLen = strlen($prefix);
            }
            // Find closest namespace
            $namespace = getClosestNode($node, Node\Stmt\Namespace_::class);
            /** Map from alias to Definition */
            $aliasedDefs = [];
            if ($namespace) {
                foreach ($namespace->stmts as $stmt) {
                    if ($stmt instanceof Node\Stmt\Use_ || $stmt instanceof Node\Stmt\GroupUse) {
                        foreach ($stmt->uses as $use) {
                            // Get the definition for the used namespace, class-like, function or constant
                            // And save it under the alias
                            $fqn = (string)Node\Name::concat($stmt->prefix ?? null, $use->name);
                            if ($def = $this->index->getDefinition($fqn)) {
                                $aliasedDefs[$use->alias] = $def;
                            }
                        }
                    } else {
                        // Use statements are always the first statements in a namespace
                        break;
                    }
                }
            }
            // If there is a prefix that does not start with a slash, suggest `use`d symbols
            if ($prefix && !$isFullyQualified) {
                // Suggest symbols that have been `use`d
                // Search the aliases for the typed-in name
                foreach ($aliasedDefs as $alias => $def) {
                    if (substr($alias, 0, $prefixLen) === $prefix) {
                        $list->items[] = CompletionItem::fromDefinition($def);
                    }
                }
            }
            // Additionally, suggest global symbols that either
            //  - start with the current namespace + prefix, if the Name node is not fully qualified
            //  - start with just the prefix, if the Name node is fully qualified
            foreach ($this->index->getDefinitions() as $fqn => $def) {
                if (
                    $def->isGlobal // exclude methods, properties etc.
                    && (
                        !$prefix
                        || (
                            ((!$namespace || $isFullyQualified) && substr($fqn, 0, $prefixLen) === $prefix)
                            || (
                                $namespace
                                && !$isFullyQualified
                                && substr($fqn, 0, $namespacedPrefixLen) === $namespacedPrefix
                            )
                        )
                    )
                    // Only suggest classes for `new`
                    && (!($node instanceof Node\Expr\New_) || $def->canBeInstantiated)
                ) {
                    $item = CompletionItem::fromDefinition($def);
                    // Find the shortest name to reference the symbol
                    if ($namespace && ($alias = array_search($def, $aliasedDefs, true)) !== false) {
                        // $alias is the name under which this definition is aliased in the current namespace
                        $item->insertText = $alias;
                    } else if ($namespace && !($prefix && $isFullyQualified)) {
                        // Insert the global FQN with trailing backslash
                        $item->insertText = '\\' . $fqn;
                    } else {
                        // Insert the FQN without trailing backlash
                        $item->insertText = $fqn;
                    }
                    $list->items[] = $item;
                }
            }
            // Suggest keywords
            if ($node instanceof Node\Name && $node->getAttribute('parentNode') instanceof Node\Expr\ConstFetch) {
                foreach (self::KEYWORDS as $keyword) {
                    if (substr($keyword, 0, $prefixLen) === $prefix) {
                        $item = new CompletionItem($keyword, CompletionItemKind::KEYWORD);
                        $item->insertText = $keyword . ' ';
                        $list->items[] = $item;
                    }
                }
            }
        } else if (
            $node instanceof Node\Expr\Variable
            || ($node && $node->getAttribute('parentNode') instanceof Node\Expr\Variable)
         ) {
            // Find variables, parameters and use statements in the scope
            // If there was only a $ typed, $node will be instanceof Node\Error
            $namePrefix = $node instanceof Node\Expr\Variable && is_string($node->name) ? $node->name : '';
            foreach ($this->suggestVariablesAtNode($node, $namePrefix) as $var) {
                $item = new CompletionItem;
                $item->kind = CompletionItemKind::VARIABLE;
                $item->label = '$' . ($var instanceof Node\Expr\ClosureUse ? $var->var : $var->name);
                $item->documentation = $this->definitionResolver->getDocumentationFromNode($var);
                $item->detail = (string)$this->definitionResolver->getTypeFromNode($var);
                $item->textEdit = new TextEdit(
                    new Range($pos, $pos),
                    stripStringOverlap($doc->getRange(new Range(new Position(0, 0), $pos)), $item->label)
                );
                $list->items[] = $item;
            }
        } else if ($node instanceof Node\Stmt\InlineHTML || $pos == new Position(0, 0)) {
            $item = new CompletionItem('<?php', CompletionItemKind::KEYWORD);
            $item->textEdit = new TextEdit(
                new Range($pos, $pos),
                stripStringOverlap($doc->getRange(new Range(new Position(0, 0), $pos)), '<?php')
            );
            $list->items[] = $item;
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
                foreach ($this->expandParentFqns($def->extends) as $parent) {
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
        while ($level && !($level instanceof Node\FunctionLike)) {
            // Walk siblings before the node
            $sibling = $level;
            while ($sibling = $sibling->getAttribute('previousSibling')) {
                // Collect all variables inside the sibling node
                foreach ($this->findVariableDefinitionsInNode($sibling, $namePrefix) as $var) {
                    $vars[$var->name] = $var;
                }
            }
            $level = $level->getAttribute('parentNode');
        }

        // If the traversal ended because a function was met,
        // also add its parameters and closure uses to the result list
        if ($level instanceof Node\FunctionLike) {
            foreach ($level->params as $param) {
                if (!isset($vars[$param->name]) && substr($param->name, 0, strlen($namePrefix)) === $namePrefix) {
                    $vars[$param->name] = $param;
                }
            }
            if ($level instanceof Node\Expr\Closure) {
                foreach ($level->uses as $use) {
                    if (!isset($vars[$use->var]) && substr($use->var, 0, strlen($namePrefix)) === $namePrefix) {
                        $vars[$use->var] = $use;
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
     * @return Node\Expr\Variable[]
     */
    private function findVariableDefinitionsInNode(Node $node, string $namePrefix = ''): array
    {
        $vars = [];
        // If the child node is a variable assignment, save it
        $parent = $node->getAttribute('parentNode');
        if (
            $node instanceof Node\Expr\Variable
            && ($parent instanceof Node\Expr\Assign || $parent instanceof Node\Expr\AssignOp)
            && is_string($node->name) // Variable variables are of no use
            && substr($node->name, 0, strlen($namePrefix)) === $namePrefix
        ) {
            $vars[] = $node;
        }
        // Iterate over subnodes
        foreach ($node->getSubNodeNames() as $attr) {
            if (!isset($node->$attr)) {
                continue;
            }
            $children = is_array($node->$attr) ? $node->$attr : [$node->$attr];
            foreach ($children as $child) {
                // Dont try to traverse scalars
                // Dont traverse functions, the contained variables are in a different scope
                if (!($child instanceof Node) || $child instanceof Node\FunctionLike) {
                    continue;
                }
                foreach ($this->findVariableDefinitionsInNode($child, $namePrefix) as $var) {
                    $vars[] = $var;
                }
            }
        }
        return $vars;
    }
}
