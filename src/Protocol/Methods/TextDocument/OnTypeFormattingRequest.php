<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The document on type formatting request is sent from the client to the server to format parts of the document during
 * typing.
 */
class OnTypeFormattingRequest extends Request
{
    /**
     * @var OnTypeFormattingParams
     */
    public $params;
}
