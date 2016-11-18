<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{
    ServerCapabilities,
    ClientCapabilities,
    TextDocumentSyncKind,
    Message,
    MessageType,
    InitializeResult,
    SymbolInformation,
    TextDocumentIdentifier
};
use AdvancedJsonRpc;
use Sabre\Event\{Loop, Promise};
use function Sabre\Event\coroutine;
use Exception;
use Throwable;
use Webmozart\Glob\Iterator\GlobIterator;
use Webmozart\Glob\Glob;
use Webmozart\PathUtil\Path;
use Sabre\Uri;

class LanguageServer extends AdvancedJsonRpc\Dispatcher
{
    /**
     * Handles textDocument/* method calls
     *
     * @var Server\TextDocument
     */
    public $textDocument;

    /**
     * Handles workspace/* method calls
     *
     * @var Server\Workspace
     */
    public $workspace;

    public $telemetry;
    public $window;
    public $completionItem;
    public $codeLens;

    /**
     * ClientCapabilities
     */
    private $clientCapabilities;

    private $protocolReader;
    private $protocolWriter;
    private $client;

    /**
     * The root project path that was passed to initialize()
     *
     * @var string
     */
    private $rootPath;
    private $project;

    public function __construct(ProtocolReader $reader, ProtocolWriter $writer)
    {
        parent::__construct($this, '/');
        $this->protocolReader = $reader;
        $this->protocolReader->on('message', function (Message $msg) {
            coroutine(function () use ($msg) {
                // Ignore responses, this is the handler for requests and notifications
                if (AdvancedJsonRpc\Response::isResponse($msg->body)) {
                    return;
                }
                $result = null;
                $error = null;
                try {
                    // Invoke the method handler to get a result
                    $result = yield $this->dispatch($msg->body);
                } catch (AdvancedJsonRpc\Error $e) {
                    // If a ResponseError is thrown, send it back in the Response
                    $error = $e;
                } catch (Throwable $e) {
                    // If an unexpected error occured, send back an INTERNAL_ERROR error response
                    $error = new AdvancedJsonRpc\Error(
                        $e->getMessage(),
                        AdvancedJsonRpc\ErrorCode::INTERNAL_ERROR,
                        null,
                        $e
                    );
                }
                // Only send a Response for a Request
                // Notifications do not send Responses
                if (AdvancedJsonRpc\Request::isRequest($msg->body)) {
                    if ($error !== null) {
                        $responseBody = new AdvancedJsonRpc\ErrorResponse($msg->body->id, $error);
                    } else {
                        $responseBody = new AdvancedJsonRpc\SuccessResponse($msg->body->id, $result);
                    }
                    $this->protocolWriter->write(new Message($responseBody));
                }
            })->otherwise('\\LanguageServer\\crash');
        });
        $this->protocolWriter = $writer;
        $this->client = new LanguageClient($reader, $writer);
    }

    /**
     * The initialize request is sent as the first request from the client to the server.
     *
     * @param int $processId The process Id of the parent process that started the server.
     * @param ClientCapabilities $capabilities The capabilities provided by the client (editor)
     * @param string|null $rootPath The rootPath of the workspace. Is null if no folder is open.
     * @return InitializeResult
     */
    public function initialize(int $processId, ClientCapabilities $capabilities, string $rootPath = null): InitializeResult
    {
        $this->rootPath = $rootPath;
        $this->clientCapabilities = $capabilities;
        $this->project = new Project($this->client, $capabilities);
        $this->textDocument = new Server\TextDocument($this->project, $this->client);
        $this->workspace = new Server\Workspace($this->project, $this->client);

        // start building project index
        if ($rootPath !== null) {
            $this->indexProject()->otherwise('\\LanguageServer\\crash');
        }

        $serverCapabilities = new ServerCapabilities();
        // Ask the client to return always full documents (because we need to rebuild the AST from scratch)
        $serverCapabilities->textDocumentSync = TextDocumentSyncKind::FULL;
        // Support "Find all symbols"
        $serverCapabilities->documentSymbolProvider = true;
        // Support "Find all symbols in workspace"
        $serverCapabilities->workspaceSymbolProvider = true;
        // Support "Format Code"
        $serverCapabilities->documentFormattingProvider = true;
        // Support "Go to definition"
        $serverCapabilities->definitionProvider = true;
        // Support "Find all references"
        $serverCapabilities->referencesProvider = true;
        // Support "Hover"
        $serverCapabilities->hoverProvider = true;

        return new InitializeResult($serverCapabilities);
    }

    /**
     * The shutdown request is sent from the client to the server. It asks the server to shut down, but to not exit
     * (otherwise the response might not be delivered correctly to the client). There is a separate exit notification that
     * asks the server to exit.
     *
     * @return void
     */
    public function shutdown()
    {
        unset($this->project);
    }

    /**
     * A notification to ask the server to exit its process.
     *
     * @return void
     */
    public function exit()
    {
        exit(0);
    }

    /**
     * Parses workspace files, one at a time.
     *
     * @return Promise <void>
     */
    private function indexProject(): Promise
    {
        return coroutine(function () {
            $textDocuments = yield $this->findPhpFiles();
            $count = count($textDocuments);

            $startTime = microtime(true);

            foreach ($textDocuments as $i => $textDocument) {
                // Give LS to the chance to handle requests while indexing
                yield timeout();
                $this->client->window->logMessage(
                    MessageType::LOG,
                    "Parsing file $i/$count: {$textDocument->uri}"
                );
                try {
                    yield $this->project->loadDocument($textDocument->uri);
                } catch (ContentTooLargeException $e) {
                    $this->client->window->logMessage(
                        MessageType::INFO,
                        "Ignoring file {$textDocument->uri} because it exceeds size limit of {$e->limit} bytes ({$e->size})"
                    );
                } catch (Exception $e) {
                    $this->client->window->logMessage(
                        MessageType::ERROR,
                        "Error parsing file {$textDocument->uri}: " . (string)$e
                    );
                }
            }

            $duration = (int)(microtime(true) - $startTime);
            $mem = (int)(memory_get_usage(true) / (1024 * 1024));
            $this->client->window->logMessage(
                MessageType::INFO,
                "All $count PHP files parsed in $duration seconds. $mem MiB allocated."
            );
        });
    }

    /**
     * Returns all PHP files in the workspace.
     * If the client does not support workspace/files, it falls back to searching the file system directly.
     *
     * @return Promise <TextDocumentIdentifier[]>
     */
    private function findPhpFiles(): Promise
    {
        return coroutine(function () {
            $textDocuments = [];
            $pattern = Path::makeAbsolute('**/*.php', $this->rootPath);
            if ($this->clientCapabilities->xfilesProvider) {
                // Use xfiles request
                foreach (yield $this->client->workspace->xfiles() as $textDocument) {
                    $path = Uri\parse($textDocument->uri)['path'];
                    if (Glob::match($path, $pattern)) {
                        $textDocuments[] = $textDocument;
                    }
                }
            } else {
                // Use the file system
                foreach (new GlobIterator($pattern) as $path) {
                    $textDocuments[] = new TextDocumentIdentifier(pathToUri($path));
                    yield timeout();
                }
            }
            return $textDocuments;
        });
    }
}
