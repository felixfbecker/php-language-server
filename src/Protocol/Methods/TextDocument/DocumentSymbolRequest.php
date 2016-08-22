<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The document symbol request is sent from the client to the server to list all symbols found in a given text document.
 */
class DocumentSymbolRequest extends Request
{
    /**
     * @var DocumentSymbolParams
     */
    public $params;
}
