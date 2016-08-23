<?php

namespace LanguageServer\Protocol\Methods\Initialize;

use LanguageServer\Protocol\Result;
use LanguageServer\Protocol\ServerCapabilities;

class InitializeResult extends Result
{
    /**
     * The capabilities the language server provides.
     *
     * @var LanguageServer\Protocol\ServerCapabilities
     */
    public $capabilites;

    /**
     * @param LanguageServer\Protocol\ServerCapabilities $capabilites
     */
    public function __construct(ServerCapabilities $capabilites = null)
    {
        $this->capabilities = $capabilites ?? new ServerCapabilities();
    }
}
