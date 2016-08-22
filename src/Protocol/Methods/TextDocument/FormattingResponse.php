<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class FormattingResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\TextEdit[]
     */
    public $result;
}
