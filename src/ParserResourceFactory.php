<?php

namespace LanguageServer;

use Microsoft\PhpParser as Tolerant;
use LanguageServer\Index\ReadableIndex;

class ParserResourceFactory {
    const PARSER_KIND = ParserKind::PHP_PARSER;
    
    public function getParser() {
        if (self::PARSER_KIND === ParserKind::PHP_PARSER) {
            return new Parser;
        } else {
            return new Tolerant\Parser;
        }
    }

    public function getDefinitionResolver(ReadableIndex $index) {
        if (self::PARSER_KIND === ParserKind::PHP_PARSER) {
            return new DefinitionResolver($index);
        } else {
            return new TolerantDefinitionResolver($index);
        }
    }
}
