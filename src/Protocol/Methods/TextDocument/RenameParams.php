<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

class RenameParams extends Params
{
    /**
     * The document to format.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The position at which this request was sent.
     *
     * @var LanguageServer\Protocol\Position
     */
    public $position;

    /**
     * The new name of the symbol. If the given name is not valid the
     * request must return a ResponseError with an
     * appropriate message set.
     *
     * @var string
     */
    public $newName;
}
