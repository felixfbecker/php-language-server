<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitors;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\Protocol\{SymbolInformation, SymbolKind, Range, Position, Location};

/**
 * Decorates all nodes with parent and sibling references (similar to DOM nodes)
 */
class ReferencesAdder extends NodeVisitorAbstract
{
    /**
     * @var Node[]
     */
    private $stack = [];

    /**
     * @var Node
     */
    private $previous;

    public function enterNode(Node $node)
    {
        if (!empty($this->stack)) {
            $node->setAttribute('parentNode', end($this->stack));
        }
        if (isset($this->previous) && $this->previous->getAttribute('parentNode') === $node->getAttribute('parentNode')) {
            $node->setAttribute('previousSibling', $this->previous);
            $this->previous->setAttribute('nextSibling', $node);
        }
        $this->stack[] = $node;
    }

    public function leaveNode(Node $node)
    {
        $this->previous = $node;
        array_pop($this->stack);
    }
}
