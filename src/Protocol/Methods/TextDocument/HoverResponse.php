<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class HoverResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\Hover
     */
    public $result;
}
