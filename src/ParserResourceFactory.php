<?php

namespace LanguageServer;

use Microsoft\PhpParser as Tolerant;
use LanguageServer\Index\ReadableIndex;

class ParserResourceFactory {
    const PARSER_KIND = ParserKind::TOLERANT_PHP_PARSER;

    private static function getParserKind () {
        global $parserKind;
        return isset($parserKind) ? $parserKind : self::PARSER_KIND;
    }

    public static function getParser() {
        if (self::getParserKind() === ParserKind::PHP_PARSER || self::getParserKind() === ParserKind::DIAGNOSTIC_PHP_PARSER) {
            return new Parser;
        } else {
            return new Tolerant\Parser;
        }
    }

    public static function getDefinitionResolver(ReadableIndex $index) {
        switch (self::getParserKind()) {
            case ParserKind::PHP_PARSER:
                return new DefinitionResolver($index);
            case ParserKind::TOLERANT_PHP_PARSER:
                return new TolerantDefinitionResolver($index);
            case ParserKind::DIAGNOSTIC_PHP_PARSER:
                return new LoggedDefinitionResolver($index);
            case ParserKind::DIAGNOSTIC_TOLERANT_PHP_PARSER:
                return new LoggedTolerantDefinitionResolver($index);
        }
    }

    public static function getTreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri)
    {
        if (self::getParserKind() === ParserKind::PHP_PARSER || self::getParserKind() === ParserKind::DIAGNOSTIC_PHP_PARSER) {
            return new TreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri);
        } else {
            return new TolerantTreeAnalyzer($parser, $content, $docBlockFactory, $definitionResolver, $uri);
        }
    }
}
