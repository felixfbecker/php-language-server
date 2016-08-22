<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

class CodeLensParams extends Params
{
    /**
     * The document to request code lens for.
     *
     * @var TextDocumentIdentifier
     */
    public $textDocument;
}
