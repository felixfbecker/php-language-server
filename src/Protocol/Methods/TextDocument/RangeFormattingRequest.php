<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The document range formatting request is sent from the client to the server to format a given range in a document.
 */
class RangeFormattingRequest extends Request
{
    /**
     * @var RangeFormattingParams
     */
    public $params;
}
