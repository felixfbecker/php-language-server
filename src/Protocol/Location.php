<?php

namespace LanguageServer\Protocol;

use Microsoft\PhpParser;
use Microsoft\PhpParser\Node;

/**
 * Represents a location inside a resource, such as a line inside a text file.
 */
class Location
{
    /**
     * @var string
     */
    public $uri;

    /**
     * @var Range
     */
    public $range;

    /**
     * Returns the location of the node
     *
     * @param Node $node
     * @return self
     */
    public static function fromNode($node)
    {
        $range = PhpParser\PositionUtilities::getRangeFromPosition($node->getStart(), $node->getWidth(), $node->getFileContents());
        return new self($node->getUri(), new Range(
            new Position($range->start->line, $range->start->character),
            new Position($range->end->line, $range->end->character)
        ));
    }

    public function __construct(string $uri = null, Range $range = null)
    {
        $this->uri = $uri;
        $this->range = $range;
    }
}
