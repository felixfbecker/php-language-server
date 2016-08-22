<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The document formatting request is sent from the server to the client to format a whole document.
 */
class FormattingRequest extends Request
{
    /**
     * @var FormattingParams
     */
    public $params;
}
