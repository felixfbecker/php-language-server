<?php

namespace LanguageServer\Protocol\Methods\Workspace;

use LanguageServer\Protocol\Response;

class SymbolResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\SymbolInformation[]
     */
    public $result;
}
