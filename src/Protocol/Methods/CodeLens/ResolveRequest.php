<?php

namespace LanguageServer\Protocol\Methods\CodeLens;

use LanguageServer\Protocol\Request;

/**
 * The code lens resolve request is sent from the client to the server to resolve the command for a given code lens
 * item.
 */
class ResolveRequest extends Request
{
    /**
     * @var LanguageServer\Protocol\CodeLens
     */
    public $params;
}
