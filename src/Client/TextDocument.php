<?php
declare(strict_types = 1);

namespace LanguageServer\Client;

use AdvancedJsonRpc\Notification as NotificationBody;
use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer};
use PhpParser\NodeVisitor\NameResolver;
use LanguageServer\ProtocolWriter;
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, VersionedTextDocumentIdentifier, Message};

/**
 * Provides method handlers for all textDocument/* methods
 */
class TextDocument
{
    /**
     * @var ProtocolWriter
     */
    private $protocolWriter;

    public function __construct(ProtocolWriter $protocolWriter)
    {
        $this->protocolWriter = $protocolWriter;
    }

    /**
     * Diagnostics notification are sent from the server to the client to signal results of validation runs.
     *
     * @param string $uri
     * @param Diagnostic[] $diagnostics
     */
    public function publishDiagnostics(string $uri, array $diagnostics)
    {
        $this->protocolWriter->write(new Message(new NotificationBody(
            'textDocument/publishDiagnostics',
            (object)[
                'uri' => $uri,
                'diagnostics' => $diagnostics
            ]
        )));
    }
}
