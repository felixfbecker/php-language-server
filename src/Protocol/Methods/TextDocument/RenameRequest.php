<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The rename request is sent from the client to the server to perform a workspace-wide rename of a symbol.
 */
class RenameRequest extends Request
{
    /**
     * @var RenameParams
     */
    public $params;
}
