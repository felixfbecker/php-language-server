<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node, NodeTraverser};

/**
 * Collects all references to a variable
 */
class VariableReferencesCollector extends NodeVisitorAbstract
{
    /**
     * Array of references to the variable
     *
     * @var Node\Expr\Variable[]
     */
    public $nodes = [];

    /**
     * @var string
     */
    private $name;

    /**
     * @param string $name The variable name
     */
    public function __construct(string $name)
    {
        $this->name = $name;
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Expr\Variable && $node->name === $this->name) {
            $this->nodes[] = $node;
        } else if ($node instanceof Node\FunctionLike) {
            // If we meet a function node, dont traverse its statements, they are in another scope
            // except it is a closure that has imported the variable through use
            if ($node instanceof Node\Expr\Closure) {
                foreach ($node->uses as $use) {
                    if ($use->var === $this->name) {
                        return;
                    }
                }
            }
            return NodeTraverser::DONT_TRAVERSE_CHILDREN;
        }
    }
}
