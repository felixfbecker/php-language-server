<?php

namespace LanguageServer\Protocol;

use PhpParser\Node;
use Microsoft\PhpParser as Tolerant;

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
     * @param Node | Tolerant\Node $node
     * @return self
     */
    public static function fromNode($node)
    {
        if ($node instanceof Node) {
            return new self($node->getAttribute('ownerDocument')->getUri(), Range::fromNode($node));
        } else {
            $range = Tolerant\PositionUtilities::getRangeFromPosition($node->getStart(), $node->getWidth(), $node->getFileContents());
            return new self($node->getUri(), new Range(
                new Position($range->start->line, $range->start->character),
                new Position($range->end->line, $range->end->character)
            ));
        }
    }

    public function __construct(string $uri = null, Range $range = null)
    {
        $this->uri = $uri;
        $this->range = $range;
    }
}
