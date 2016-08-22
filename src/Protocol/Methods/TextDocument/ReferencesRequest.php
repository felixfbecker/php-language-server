<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The references request is sent from the client to the server to resolve project-wide references for the symbol
 * denoted by the given text document position.
 */
class ReferencesRequest extends Request
{
    /**
     * @var ReferenceParams
     */
    public $params;
}
