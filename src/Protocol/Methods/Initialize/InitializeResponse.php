<?php

namespace LanguageServer\Protocol\Methods\Initialize;

use LanguageServer\Protocol\Response;

class InitializeResponse extends Response
{
    /**
     * The capabilities the language server provides.
     *
     * @var ServerCapabilities
     */
    public $capabilites;
}
