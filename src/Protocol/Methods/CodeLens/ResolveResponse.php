<?php

namespace LanguageServer\Protocol\Methods\CodeLens;

use LanguageServer\Protocol\Response;

class ResolveResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\CodeLens
     */
    public $result;
}
