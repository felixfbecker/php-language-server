<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class ReferencesResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\Location[]
     */
    public $result;
}
