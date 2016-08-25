<?php

namespace LanguageServer\Protocol;

class InitializeResult
{
    /**
     * The capabilities the language server provides.
     *
     * @var LanguageServer\Protocol\ServerCapabilities
     */
    public $capabilities;

    /**
     * @param LanguageServer\Protocol\ServerCapabilities $capabilities
     */
    public function __construct(ServerCapabilities $capabilities = null)
    {
        $this->capabilities = $capabilities ?? new ServerCapabilities();
    }
}
