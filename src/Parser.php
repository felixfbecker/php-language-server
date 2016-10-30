<?php

namespace LanguageServer;

use PhpParser;

/**
 * Custom PHP Parser class configured for our needs
 */
class Parser extends PhpParser\Parser\Php7
{
    public function __construct()
    {
        $lexer = new PhpParser\Lexer([
            'usedAttributes' => [
                'comments',
                'startLine',
                'endLine',
                'startFilePos',
                'endFilePos'
            ]
        ]);
        parent::__construct($lexer);
    }
}
