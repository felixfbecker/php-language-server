<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

class SignatureHelpRequest extends Request
{
    /**
     * @var PositionParams
     */
    public $params;
}
