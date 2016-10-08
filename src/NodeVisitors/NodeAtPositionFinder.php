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
        $range = new Range(
            new Position($node->getAttribute('startLine') - 1, $node->getAttribute('startColumn') - 1),
            new Position($node->getAttribute('endLine') - 1, $node->getAttribute('endColumn') - 1)
        );
        if (!isset($this->node) && $range->includes($this->position)) {
            $this->node = $node;
        }
    }
}
