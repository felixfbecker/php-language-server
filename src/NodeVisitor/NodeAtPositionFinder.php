<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node, NodeTraverser};
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
     * @var Node|null
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
        if ($this->node === null) {
            $range = Range::fromNode($node);
            if ($range->includes($this->position)) {
                $this->node = $node;
                return NodeTraverser::STOP_TRAVERSAL;
            }
        }
    }
}
