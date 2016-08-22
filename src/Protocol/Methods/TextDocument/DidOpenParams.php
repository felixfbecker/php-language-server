<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Params;

class DidOpenTextDocumentParams extends Params
{
    /**
     * The document that was opened.
     *
     * @var LanguageServer\Protocol\TextDocumentItem
     */
    public $textDocument;
}
