<?php

namespace LanguageServer;


use LanguageServer\Index\ReadableIndex;

class DefinitionResolverFactory
{
    public static function create(ReadableIndex $index, int $parserKind = ParserKind::PHP_PARSER) : DefinitionResolverInterface
    {
        if ($parserKind === ParserKind::PHP_PARSER) {
            return new DefinitionResolver($index);
        } elseif ($parserKind === ParserKind::TOLERANT_PHP_PARSER) {
            return new TolerantDefinitionResolver($index);
        }
        throw new \Exception("Unhandled parser kind");
    }
}