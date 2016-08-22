<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class CompletionResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\CompletionItem[]|LanguageServer\Protocol\CompletionList
     */
    public $result;
}
