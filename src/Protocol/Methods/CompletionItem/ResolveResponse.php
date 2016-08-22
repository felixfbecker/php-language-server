<?php

namespace LanguageServer\Protocol\Methods\CompletionItem;

use LanguageServer\Protocol\Response;

class ResolveResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\CompletionItem
     */
    public $result;
}
