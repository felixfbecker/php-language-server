<?php

namespace LanguageServer;

use Microsoft\PhpParser as Tolerant;
use LanguageServer\Index\ReadableIndex;

class ParserResourceFactory {
    const PARSER_KIND = ParserKind::TOLERANT_PHP_PARSER;
    
    public static function getParser() {
        if (self::PARSER_KIND === ParserKind::PHP_PARSER) {
            return new Parser;
        } else {
            return new Tolerant\Parser;
        }
    }

    public static function getDefinitionResolver(ReadableIndex $index) {
        if (self::PARSER_KIND === ParserKind::PHP_PARSER) {
            return new DefinitionResolver($index);
        } elseif (self::PARSER_KIND === ParserKind::TOLERANT_PHP_PARSER) {
            return new TolerantDefinitionResolver($index);
        } elseif (self::PARSER_KIND === ParserKind::DIAGNOSTIC_TOLERANT_PHP_PARSER) {
            return new LoggedTolerantDefinitionResolver($index);
        }
    }

    public static function getTreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri)
    {
        if (self::PARSER_KIND === ParserKind::PHP_PARSER) {
            return new TreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri);
        } else {
            return new TolerantTreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri);
        }
    }
}
