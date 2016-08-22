<?php

namespace LanguageServer\Protocol\Methods\Initialize;

class InitializeParams
{

    /**
     * The rootPath of the workspace. Is null if no folder is open.
     *
     * @var string
     */
    public $rootPath;

    /**
     * The process Id of the parent process that started the server.
     *
     * @var number
     */
    public $processId;

    /**
     * The capabilities provided by the client (editor)
     *
     * @var ClientCapabilities
     */
    public $capabilities;
}
