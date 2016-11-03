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
    SymbolInformation
};
use AdvancedJsonRpc;
use Sabre\Event\Loop;
use function Sabre\Event\coroutine;
use Exception;
use Throwable;
use Generator;
use Webmozart\Glob\Iterator\GlobIterator;
use Webmozart\PathUril\Path;

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
                    $result = $this->dispatch($msg->body);
                    if ($result instanceof Generator) {
                        $result = yield from $result;
                    } else if ($result instanceof Promise) {
                        $result = yield $result;
                    }
                } catch (AdvancedJsonRpc\Error $e) {
                    // If a ResponseError is thrown, send it back in the Response
                    $error = $e;
                } catch (Throwable $e) {
                    // If an unexpected error occured, send back an INTERNAL_ERROR error response
                    $error = new AdvancedJsonRpc\Error((string)$e, AdvancedJsonRpc\ErrorCode::INTERNAL_ERROR, null, $e);
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
            })->otherwise(function ($err) {
                // Crash process
                Loop\nextTick(function () use ($err) {
                    throw $err;
                });
            });
        });
        $this->protocolWriter = $writer;
        $this->client = new LanguageClient($reader, $writer);

        $this->project = new Project($this->client);

        $this->textDocument = new Server\TextDocument($this->project, $this->client);
        $this->workspace = new Server\Workspace($this->project, $this->client);
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

        // start building project index
        if ($rootPath !== null) {
            $this->indexProject()->otherwise(function ($err) {
                // Crash process
                Loop\nextTick(function () use ($err) {
                    throw $err;
                });
            });
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
    private function indexProject()
    {
        return coroutine(function () {
            $textDocuments = yield $this->globWorkspace('**/*.php');
            $count = count($uris);

            $startTime = microtime(true);

            foreach ($textDocuments as $i => $textDocument) {
                // Give LS to the chance to handle requests while indexing
                Loop\tick();
                $this->client->window->logMessage(MessageType::INFO, "Parsing file $i/$count: {$textDocument->uri}");
                try {
                    $this->project->loadDocument($uri);
                } catch (Exception $e) {
                    $this->client->window->logMessage(MessageType::ERROR, "Error parsing file $shortName: " . (string)$e);
                }
            }

            $duration = (int)(microtime(true) - $startTime);
            $mem = (int)(memory_get_usage(true) / (1024 * 1024));
            $this->client->window->logMessage(MessageType::INFO, "All PHP files parsed in $duration seconds. $mem MiB allocated.");
        });
    }

    /**
     * Returns all files matching a glob pattern.
     * If the client does not support workspace/xglob, it falls back to globbing the file system directly.
     *
     * @param string $pattern
     * @return Promise <TextDocumentIdentifier[]>
     */
    private function globWorkspace(string $pattern): Promise
    {
        if ($this->clientCapabilities->xglobProvider) {
            // Use xglob request
            return $this->client->workspace->xglob($pattern);
        } else {
            // Use the file system
            $promise = new Promise;
            $textDocuments = [];
            $pattern = Path::makeAbsolute($pattern, $this->rootPath);
            $iterator = new GlobIterator($pattern);
            $next = function () use ($iterator, &$textDocuments, $promise, &$next) {
                if (!$iterator->valid()) {
                    $promise->resolve($textDocuments);
                    return;
                }
                $textDocuments[] = new TextDocumentIdentifier(pathToUri($iterator->current()));
                $iterator->next();
                Loop\setTimeout($next, 0);
            };
            Loop\setTimeout($next, 0);
            return $promise;
        }
    }
}
