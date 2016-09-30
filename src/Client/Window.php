<?php
declare(strict_types = 1);

namespace LanguageServer\Client;

use AdvancedJsonRpc\Notification as NotificationBody;
use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer};
use PhpParser\NodeVisitor\NameResolver;
use LanguageServer\ProtocolWriter;
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, VersionedTextDocumentIdentifier, Message};

/**
 * Provides method handlers for all window/* methods
 */
class Window
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
     * The show message notification is sent from a server to a client to ask the client to display a particular message in the user interface.
     *
     * @param int $type
     * @param string $message
     */
    public function showMessage(int $type, string $message)
    {
        $this->protocolWriter->write(new Message(new NotificationBody(
            'window/showMessage',
            (object)[
                'type' => $type,
                'message' => $message
            ]
        )));
    }

    /**
     * The log message notification is sent from the server to the client to ask the client to log a particular message.
     *
     * @param int $type
     * @param string $message
     */
    public function logMessage(int $type, string $message)
    {
        $this->protocolWriter->write(new Message(new NotificationBody(
            'window/logMessage',
            (object)[
                'type' => $type,
                'message' => $message
            ]
        )));
    }
}
