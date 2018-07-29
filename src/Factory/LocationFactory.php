<?php

namespace LanguageServer\Factory;

use LanguageServer\Protocol\Location;
use LanguageServer\Protocol\Position;
use LanguageServer\Protocol\Range;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\PositionUtilities;

class LocationFactory
{
    /**
     * Returns the location of the node
     *
     * @param Node $node
     * @return self
     */
    public static function fromNode(Node $node): Location
    {
        $range = PositionUtilities::getRangeFromPosition(
            $node->getStart(),
            $node->getWidth(),
            $node->getFileContents()
        );

        return new Location($node->getUri(), new Range(
            new Position($range->start->line, $range->start->character),
            new Position($range->end->line, $range->end->character)
        ));
    }
}
