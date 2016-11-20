<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\Node;
use phpDocumentor\Reflection\Types;
use LanguageServer\Protocol\{
    Position,
    SymbolKind,
    CompletionItem,
    CompletionItemKind
};

class CompletionProvider
{
    /**
     * @var DefinitionResolver
     */
    private $definitionResolver;

    /**
     * @var Project
     */
    private $project;

    /**
     * @param DefinitionResolver $definitionResolver
     * @param Project $project
     */
    public function __construct(DefinitionResolver $definitionResolver, Project $project)
    {
        $this->definitionResolver = $definitionResolver;
        $this->project = $project;
    }

    /**
     * Returns suggestions for a specific cursor position in a document
     *
     * @param PhpDocument $document The opened document
     * @param Position $position The cursor position
     * @return CompletionItem[]
     */
    public function provideCompletion(PhpDocument $document, Position $position): array
    {
        $node = $document->getNodeAtPosition($position);

        /** @var CompletionItem[] */
        $items = [];

        if ($node instanceof Node\Expr\Error) {
            $node = $node->getAttribute('parentNode');
        }

        // If we get a property fetch node, resolve items of the class
        if ($node instanceof Node\Expr\PropertyFetch) {
            $objType = $this->definitionResolver->resolveExpressionNodeToType($node->var);
            if ($objType instanceof Types\Object_ && $objType->getFqsen() !== null) {
                $prefix = substr((string)$objType->getFqsen(), 1) . '::';
                if (is_string($node->name)) {
                    $prefix .= $node->name;
                }
                $prefixLen = strlen($prefix);
                foreach ($this->project->getDefinitions() as $fqn => $def) {
                    if (substr($fqn, 0, $prefixLen) === $prefix) {
                        $item = new CompletionItem;
                        $item->label = $def->symbolInformation->name;
                        if ($def->type) {
                            $item->detail = (string)$def->type;
                        }
                        if ($def->documentation) {
                            $item->documentation = $def->documentation;
                        }
                        if ($def->symbolInformation->kind === SymbolKind::PROPERTY) {
                            $item->kind = CompletionItemKind::PROPERTY;
                        } else if ($def->symbolInformation->kind === SymbolKind::METHOD) {
                            $item->kind = CompletionItemKind::METHOD;
                        }
                        $items[] = $item;
                    }
                }
            }
        } else {
            // Find variables, parameters and use statements in the scope
            foreach ($this->suggestVariablesAtNode($node) as $var) {
                $item = new CompletionItem;
                $item->kind = CompletionItemKind::VARIABLE;
                $item->documentation = $this->definitionResolver->getDocumentationFromNode($var);
                if ($var instanceof Node\Param) {
                    $item->label = '$' . $var->name;
                    $item->detail = (string)$this->definitionResolver->getTypeFromNode($var); // TODO make it handle variables as well. Makes sense because needs to handle @var tag too!
                } else if ($var instanceof Node\Expr\Variable || $var instanceof Node\Expr\ClosureUse) {
                    $item->label = '$' . ($var instanceof Node\Expr\ClosureUse ? $var->var : $var->name);
                    $item->detail = (string)$this->definitionResolver->resolveExpressionNodeToType($var->getAttribute('parentNode'));
                } else {
                    throw new \LogicException;
                }
                $items[] = $item;
            }
        }

        return $items;
    }

    /**
     * Will walk the AST upwards until a function-like node is met
     * and at each level walk all previous siblings and their children to search for definitions
     * of that variable
     *
     * @param Node $node
     * @return array <Node\Expr\Variable|Node\Param|Node\Expr\ClosureUse>
     */
    private function suggestVariablesAtNode(Node $node): array
    {
        $vars = [];

        // Find variables in the node itself
        // When getting completion in the middle of a function, $node will be the function node
        // so we need to search it
        foreach ($this->findVariableDefinitionsInNode($node) as $var) {
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
                foreach ($this->findVariableDefinitionsInNode($sibling) as $var) {
                    $vars[$var->name] = $var;
                }
            }
            $level = $level->getAttribute('parentNode');
        }

        // If the traversal ended because a function was met,
        // also add its parameters and closure uses to the result list
        if ($level instanceof Node\FunctionLike) {
            foreach ($level->params as $param) {
                if (!isset($vars[$param->name])) {
                    $vars[$param->name] = $param;
                }
            }
            if ($level instanceof Node\Expr\Closure) {
                foreach ($level->uses as $use) {
                    if (!isset($vars[$param->name])) {
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
     * @return Node\Expr\Variable[]
     */
    private function findVariableDefinitionsInNode(Node $node): array
    {
        $vars = [];
        // If the child node is a variable assignment, save it
        $parent = $node->getAttribute('parentNode');
        if (
            $node instanceof Node\Expr\Variable
            && ($parent instanceof Node\Expr\Assign || $parent instanceof Node\Expr\AssignOp)
            && is_string($node->name) // Variable variables are of no use
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
                foreach ($this->findVariableDefinitionsInNode($child) as $var) {
                    $vars[] = $var;
                }
            }
        }
        return $vars;
    }
}
