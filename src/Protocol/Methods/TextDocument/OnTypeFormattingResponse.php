<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class OnTypeFormattingResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\TextEdit[]
     */
    public $result;
}
