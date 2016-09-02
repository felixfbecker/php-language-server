<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Client\TextDocument;

class LanguageClient
{
    /**
     * Handles textDocument/* methods
     *
     * @var Client\TextDocument
     */
    public $textDocument;

    private $protocolWriter;

    public function __construct(ProtocolWriter $writer)
    {
        $this->protocolWriter = $writer;
        $this->textDocument = new TextDocument($writer);
    }
}
