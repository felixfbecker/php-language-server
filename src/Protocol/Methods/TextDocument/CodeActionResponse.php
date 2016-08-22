<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class CodeActionResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\Command[]
     */
    public $result;
}
