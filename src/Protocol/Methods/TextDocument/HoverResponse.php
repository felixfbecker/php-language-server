<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Response;

class HoverResponse extends Response
{
    /**
     * @var Hover
     */
    public $result;
}
