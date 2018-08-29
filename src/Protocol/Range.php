<?php

namespace LanguageServer\Protocol;

use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;

/**
 * A range in a text document expressed as (zero-based) start and end positions.
 */
class Range
{
    /**
     * The range's start position.
     *
     * @var Position
     */
    public $start;

    /**
     * The range's end position.
     *
     * @var Position
     */
    public $end;

    /**
     * Returns the range the node spans
     *
     * @param Node $node
     * @return self
     */
    public static function fromNode(Node $node)
    {
        $range = PhpParser\PositionUtilities::getRangeFromPosition($node->getStart(), $node->getWidth(), $node->getFileContents());

        return new self(
            new Position($range->start->line, $range->start->character),
            new Position($range->end->line, $range->end->character)
        );
    }

    public function __construct(Position $start = null, Position $end = null)
    {
        $this->start = $start;
        $this->end = $end;
    }

    /**
     * Checks if a position is within the range
     *
     * @param Position $position
     * @return bool
     */
    public function includes(Position $position): bool
    {
        return $this->start->compare($position) <= 0 && $this->end->compare($position) >= 0;
    }
}
