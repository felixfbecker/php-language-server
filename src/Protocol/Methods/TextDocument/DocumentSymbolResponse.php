<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class DocumentSymbolResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\SymbolInformation[]
     */
    public $result;
}
