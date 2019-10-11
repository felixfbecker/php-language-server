<?php

namespace LanguageServer\Factory;

use LanguageServerProtocol\Location;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\Range;
use Microsoft\PhpParser\Node;
use Microsoft\PhpParser\Token;
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

    /**
     * Returns the location of the token
     *
     * @param Token $node
     * @return self
     */
    public static function fromToken(Node $node, Token $token): Location
    {
        $range = PositionUtilities::getRangeFromPosition(
            $token->getStartPosition(),
            $token->getWidth(),
            $node->getFileContents()
        );

        return new Location($node->getUri(), new Range(
            new Position($range->start->line, $range->start->character),
            new Position($range->end->line, $range->end->character)
        ));
    }
}
