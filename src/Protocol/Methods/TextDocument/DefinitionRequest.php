<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The goto definition request is sent from the client to the server to resolve the definition location of a symbol at a
 * given text document position.
 */
class DefinitionRequest extends Request
{
    /**
     * @var PositionParams
     */
    public $params;
}
