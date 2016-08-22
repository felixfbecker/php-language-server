<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The code lens request is sent from the client to the server to compute code lenses for a given text document.
 */
class CodeLensRequest extends Request
{
    /**
     * @var CodeLensParams
     */
    public $params;
}
