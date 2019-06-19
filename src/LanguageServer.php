<?php
declare(strict_types=1);

namespace LanguageServer;

use AdvancedJsonRpc;
use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\Promise;
use LanguageServer\Cache\{ClientCache, FileSystemCache};
use LanguageServer\ContentRetriever\{ClientContentRetriever, ContentRetriever, FileSystemContentRetriever};
use LanguageServer\Event\MessageEvent;
use LanguageServer\FilesFinder\{ClientFilesFinder, FilesFinder, FileSystemFilesFinder};
use LanguageServer\Index\{DependenciesIndex, GlobalIndex, Index, ProjectIndex, StubsIndex};
use LanguageServerProtocol\{ClientCapabilities,
    CompletionOptions,
    InitializeResult,
    ServerCapabilities,
    SignatureHelpOptions,
    TextDocumentSyncKind};
use Throwable;
use Webmozart\PathUtil\Path;

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
    public $completionItem;
    public $codeLens;

    /**
     * @var ProtocolReader
     */
    protected $protocolReader;

    /**
     * @var ProtocolWriter
     */
    protected $protocolWriter;

    /**
     * @var LanguageClient
     */
    protected $client;

    /**
     * @var FilesFinder
     */
    protected $filesFinder;

    /**
     * @var ContentRetriever
     */
    protected $contentRetriever;

    /**
     * @var PhpDocumentLoader
     */
    protected $documentLoader;

    /**
     * The parsed composer.json file in the project, if any
     *
     * @var \stdClass
     */
    protected $composerJson;

    /**
     * The parsed composer.lock file in the project, if any
     *
     * @var \stdClass
     */
    protected $composerLock;

    /**
     * @var GlobalIndex
     */
    protected $globalIndex;

    /**
     * @var ProjectIndex
     */
    protected $projectIndex;

    /**
     * @var DefinitionResolver
     */
    protected $definitionResolver;

    /**
     * @var Deferred
     */
    private $shutdownDeferred;

    /**
     * @param ProtocolReader $reader
     * @param ProtocolWriter $writer
     */
    public function __construct(ProtocolReader $reader, ProtocolWriter $writer)
    {
        parent::__construct($this, '/');

        $this->shutdownDeferred = new Deferred();

        $this->protocolReader = $reader;
        $this->protocolReader->addListener('close', function () {
            $this->shutdown();
        });
        $this->protocolWriter = $writer;
        $this->client = new LanguageClient($reader, $writer);
        $this->protocolReader->addListener('message', function (MessageEvent $messageEvent) use ($reader, $writer) {
            $msg = $messageEvent->getMessage();
            Loop::defer(function () use ($msg) {
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
                    // If an unexpected error occurred, send back an INTERNAL_ERROR error response
                    $error = new AdvancedJsonRpc\Error(
                        (string)$e,
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
                    yield from $this->protocolWriter->write(new Message($responseBody));
                }
            });
        });
    }

    public function getshutdownDeferred(): Promise
    {
        return $this->shutdownDeferred->promise();
    }

    /**
     * The initialize request is sent as the first request from the client to the server.
     *
     * @param ClientCapabilities $capabilities The capabilities provided by the client (editor)
     * @param string|null $rootPath The rootPath of the workspace. Is null if no folder is open.
     * @param int|null $processId The process Id of the parent process that started the server. Is null if the process has not been started by another process. If the parent process is not alive then the server should exit (see exit notification) its process.
     * @param string|null $rootUri
     * @return Promise <InitializeResult>
     */
    public function initialize(ClientCapabilities $capabilities, string $rootPath = null, int $processId = null, string $rootUri = null): Promise
    {
        $deferred = new Deferred();
        Loop::defer(function () use ($deferred, $capabilities, $rootPath, $processId, $rootUri) {
            if ($capabilities->xfilesProvider) {
                $this->filesFinder = new ClientFilesFinder($this->client);
            } else {
                $this->filesFinder = new FileSystemFilesFinder;
            }

            if ($capabilities->xcontentProvider) {
                $this->contentRetriever = new ClientContentRetriever($this->client);
            } else {
                $this->contentRetriever = new FileSystemContentRetriever;
            }

            $dependenciesIndex = new DependenciesIndex;
            $sourceIndex = new Index;
            $this->projectIndex = new ProjectIndex($sourceIndex, $dependenciesIndex, $this->composerJson);
            $stubsIndex = StubsIndex::read();
            $this->globalIndex = new GlobalIndex($stubsIndex, $this->projectIndex);

            // The DefinitionResolver should look in stubs, the project source and dependencies
            $this->definitionResolver = new DefinitionResolver($this->globalIndex);

            $this->documentLoader = new PhpDocumentLoader(
                $this->contentRetriever,
                $this->projectIndex,
                $this->definitionResolver
            );

            if ($rootPath !== null) {
                yield from $this->beforeIndex($rootPath);

                // Find composer.json
                if ($this->composerJson === null) {
                    $composerJsonFiles = yield from $this->filesFinder->find(Path::makeAbsolute('**/composer.json', $rootPath));
                    sortUrisLevelOrder($composerJsonFiles);

                    if (!empty($composerJsonFiles)) {
                        $this->composerJson = json_decode(yield from $this->contentRetriever->retrieve($composerJsonFiles[0]));
                    }
                }

                // Find composer.lock
                if ($this->composerLock === null) {
                    $composerLockFiles = yield from $this->filesFinder->find(Path::makeAbsolute('**/composer.lock', $rootPath));
                    sortUrisLevelOrder($composerLockFiles);

                    if (!empty($composerLockFiles)) {
                        $this->composerLock = json_decode(yield from $this->contentRetriever->retrieve($composerLockFiles[0]));
                    }
                }

                $cache = $capabilities->xcacheProvider ? new ClientCache($this->client) : new FileSystemCache;

                // Index in background
                $indexer = new Indexer(
                    $this->filesFinder,
                    $rootPath,
                    $this->client,
                    $cache,
                    $dependenciesIndex,
                    $sourceIndex,
                    $this->documentLoader,
                    $this->composerLock,
                    $this->composerJson
                );
                Loop::defer(function () use ($indexer) {
                    yield from $indexer->index();
                });
            }


            if ($this->textDocument === null) {
                $this->textDocument = new Server\TextDocument(
                    $this->documentLoader,
                    $this->definitionResolver,
                    $this->client,
                    $this->globalIndex,
                    $this->composerJson,
                    $this->composerLock
                );
            }
            if ($this->workspace === null) {
                $this->workspace = new Server\Workspace(
                    $this->client,
                    $this->projectIndex,
                    $dependenciesIndex,
                    $sourceIndex,
                    $this->composerLock,
                    $this->documentLoader,
                    $this->composerJson
                );
            }

            $serverCapabilities = new ServerCapabilities();
            // Ask the client to return always full documents (because we need to rebuild the AST from scratch)
            $serverCapabilities->textDocumentSync = TextDocumentSyncKind::FULL;
            // Support "Find all symbols"
            $serverCapabilities->documentSymbolProvider = true;
            // Support "Find all symbols in workspace"
            $serverCapabilities->workspaceSymbolProvider = true;
            // Support "Go to definition"
            $serverCapabilities->definitionProvider = true;
            // Support "Find all references"
            $serverCapabilities->referencesProvider = true;
            // Support "Hover"
            $serverCapabilities->hoverProvider = true;
            // Support "Completion"
            $serverCapabilities->completionProvider = new CompletionOptions;
            $serverCapabilities->completionProvider->resolveProvider = false;
            $serverCapabilities->completionProvider->triggerCharacters = ['$', '>'];

            $serverCapabilities->signatureHelpProvider = new SignatureHelpOptions();
            $serverCapabilities->signatureHelpProvider->triggerCharacters = ['(', ','];

            // Support global references
            $serverCapabilities->xworkspaceReferencesProvider = true;
            $serverCapabilities->xdefinitionProvider = true;
            $serverCapabilities->xdependenciesProvider = true;

            $deferred->resolve(new InitializeResult($serverCapabilities));
        });
        return $deferred->promise();
    }

    /**
     * The shutdown request is sent from the client to the server. It asks the server to shut down, but to not exit
     * (otherwise the response might not be delivered correctly to the client). There is a separate exit notification that
     * asks the server to exit.
     *
     * @return \Generator
     */
    public function shutdown()
    {
        unset($this->project);
        $this->shutdownDeferred->resolve();
        yield new Delayed(0);
    }

    /**
     * Called before indexing, can return a Promise
     *
     * @param string $rootPath
     * @return \Generator
     */
    protected function beforeIndex(string $rootPath)
    {
        yield new Delayed(0, null);
    }
}
