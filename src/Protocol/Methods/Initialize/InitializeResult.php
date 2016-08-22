<?php

namespace LanguageServer\Protocol\Methods\Initialize;

use LanguageServer\Protocol\Result;

class InitializeResult extends Result
{
    /**
     * The capabilities the language server provides.
     *
     * @var LanguageServer\Protocol\ServerCapabilities
     */
    public $capabilites;
}
