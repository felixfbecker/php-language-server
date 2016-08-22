<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Params;

class DidCloseTextDocumentParams extends Params
{
    /**
     * The document that was closed.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
     */
    public $textDocument;
}
