<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitors;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\Protocol\Position;

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
        if (
            !isset($this->node)
            && $node->getAttribute('startLine') <= $this->position->line + 1
            && $node->getAttribute('endLine') >= $this->position->line + 1
            && $node->getAttribute('startColumn') <= $this->position->character + 1
            && $node->getAttribute('endColumn') >= $this->position->character + 1
        ) {
            $this->node = $node;
        }
    }
}
