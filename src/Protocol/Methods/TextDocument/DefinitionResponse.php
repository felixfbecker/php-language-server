<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class DefinitionResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\Location[]|LanguageServer\Protocol\Location
     */
    public $result;
}
