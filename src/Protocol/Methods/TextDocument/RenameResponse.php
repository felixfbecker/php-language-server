<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class RenameResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\WorkspaceEdit
     */
    public $result;
}
