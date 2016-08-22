<?php

namespace LanguageServer\Protocol\Methods\Workspace;

use LanguageServer\Protocol\Request;

class SymbolRequest extends Request
{
    /**
     * @var SymbolParams
     */
    public $params;
}
