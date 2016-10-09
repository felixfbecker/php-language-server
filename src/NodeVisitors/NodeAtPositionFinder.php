<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitors;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\Protocol\{Position, Range};

/**
 * Finds the Node at a specified position
 * Depends on ColumnCalculator
 */
class NodeAtPositionFinder extends NodeVisitorAbstract
{
    /**
     * The node at the position, if found
     *
     * @var Node
     */
    public $node;

    /**
     * @var Position
     */
    private $position;

    /**
     * @param Position $position The position where the node is located
     */
    public function __construct(Position $position)
    {
        $this->position = $position;
    }

    public function leaveNode(Node $node)
    {
        $range = Range::fromNode($node);
        // Workaround for https://github.com/nikic/PHP-Parser/issues/311
        $parent = $node->getAttribute('parentNode');
        if (isset($parent) && $parent instanceof Node\Stmt\GroupUse && $parent->prefix === $node) {
            return;
        }
        if (!isset($this->node) && $range->includes($this->position)) {
            $this->node = $node;
        }
    }
}
