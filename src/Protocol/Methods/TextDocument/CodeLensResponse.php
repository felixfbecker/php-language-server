<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class CodeLensResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\CodeLens[]
     */
    public $result;
}
