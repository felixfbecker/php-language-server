<?php
declare(strict_types = 1);

namespace LanguageServer;

class LanguageClient
{
    /**
     * Handles textDocument/* methods
     *
     * @var Client\TextDocument
     */
    public $textDocument;

    /**
     * Handles window/* methods
     *
     * @var Client\Window
     */
    public $window;

    public function __construct(ProtocolReader $reader, ProtocolWriter $writer)
    {
        $handler = new ClientHandler($reader, $writer);

        $this->textDocument = new Client\TextDocument($handler);
        $this->window = new Client\Window($handler);
    }
}
