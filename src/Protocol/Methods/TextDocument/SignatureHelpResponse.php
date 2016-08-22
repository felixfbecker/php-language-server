<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Response;

class SignatureHelpResponse extends Response
{
    /**
     * @var LanguageServer\Protocol\SignatureHelp
     */
    public $result;
}
