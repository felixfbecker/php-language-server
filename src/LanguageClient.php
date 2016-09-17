<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Client\TextDocument;
use LanguageServer\Client\Window;

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
    
    private $protocolWriter;

    public function __construct(ProtocolWriter $writer)
    {
        $this->protocolWriter = $writer;
        $this->textDocument = new TextDocument($writer);
        $this->window = new Window($writer);
    }
}
