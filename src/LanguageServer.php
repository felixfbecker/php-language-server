<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Server\TextDocument;
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
use JsonMapper;
use Exception;
use Throwable;

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
        $this->protocolReader->onMessage(function (Message $msg) {
            $result = null;
            $error = null;
            try {
                // Invoke the method handler to get a result
                $result = $this->dispatch($msg->body);
            } catch (AdvancedJsonRpc\Error $e) {
                // If a ResponseError is thrown, send it back in the Response
                $error = $e;
            } catch (Throwable $e) {
                // If an unexpected error occured, send back an INTERNAL_ERROR error response
                $error = new AdvancedJsonRpc\Error($e->getMessage(), AdvancedJsonRpc\ErrorCode::INTERNAL_ERROR, null, $e);
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
        });
        $this->protocolWriter = $writer;
        $this->client = new LanguageClient($writer);

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

        // start building project index
        if ($rootPath !== null) {
            $this->restoreCache();
            $this->indexProject();
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
        if ($this->rootPath !== null) {
            $this->saveCache();
        }
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
     * @return void
     */
    private function indexProject()
    {
        $fileList = findFilesRecursive($this->rootPath, '/^.+\.php$/i');
        $numTotalFiles = count($fileList);

        $startTime = microtime(true);
        $fileNum = 0;

        $processFile = function() use (&$fileList, &$fileNum, &$processFile, $numTotalFiles, $startTime) {
            if ($fileNum < $numTotalFiles) {
                $file = $fileList[$fileNum];
                $uri = pathToUri($file);
                $fileNum++;
                $shortName = substr($file, strlen($this->rootPath) + 1);
                $this->client->window->logMessage(MessageType::INFO, "Parsing file $fileNum/$numTotalFiles: $shortName.");

                if (filesize($file) > 500000) {
                    $this->client->window->logMessage(MessageType::INFO, "Not parsing $shortName because it exceeds size limit of 0.5MB");
                } else {
                    $this->client->window->logMessage(MessageType::INFO, "Parsing file $fileNum/$numTotalFiles: $shortName.");
                    try {
                        $this->project->loadDocument($uri);
                    } catch (Exception $e) {
                        $this->client->window->logMessage(MessageType::ERROR, "Error parsing file $shortName: " . $e->getMessage());
                    }
                }

                if ($fileNum % 1000 === 0) {
                    $this->saveCache();
                }

                Loop\setTimeout($processFile, 0);
            } else {
                $duration = (int)(microtime(true) - $startTime);
                $mem = (int)(memory_get_usage(true) / (1024 * 1024));
                $this->client->window->logMessage(MessageType::INFO, "All PHP files parsed in $duration seconds. $mem MiB allocated.");
                $this->saveCache();
            }
        };

        Loop\setTimeout($processFile, 0);
    }

    /**
     * Restores the definition and reference index from the .phpls cache directory, if available
     *
     * @return void
     */
    public function restoreCache()
    {
        $cacheDir = $this->rootPath . '/.phpls';
        if (is_dir($cacheDir)) {
            if (file_exists($cacheDir . '/symbols.json')) {
                $json = json_decode(file_get_contents($cacheDir . '/symbols.json'));
                $mapper = new JsonMapper;
                $symbols = $mapper->mapArray($json, [], SymbolInformation::class);
                $count = count($symbols);
                $this->project->setSymbols($symbols);
                $this->client->window->logMessage(MessageType::INFO, "Restoring $count symbols");
            }
            if (file_exists($cacheDir . '/references.json')) {
                $references = json_decode(file_get_contents($cacheDir . '/references.json'), true);
                $count = array_sum(array_map('count', $references));
                $this->project->setReferenceUris($references);
                $this->client->window->logMessage(MessageType::INFO, "Restoring $count references");
            }
        } else {
            $this->client->window->logMessage(MessageType::INFO, 'No cache found');
        }
    }

    /**
     * Saves the definition and reference index to the .phpls cache directory
     *
     * @return void
     */
    public function saveCache()
    {
        // Cache definitions, references
        $cacheDir = $this->rootPath . '/.phpls';
        if (!is_dir($cacheDir)) {
            mkdir($cacheDir);
        }

        $symbols = $this->project->getSymbols();
        $count = count($symbols);
        $this->client->window->logMessage(MessageType::INFO, "Saving $count symbols to cache");
        file_put_contents($cacheDir . "/symbols.json", json_encode($symbols, JSON_UNESCAPED_SLASHES));

        $references = $this->project->getReferenceUris();
        $count = array_sum(array_map('count', $references));
        $this->client->window->logMessage(MessageType::INFO, "Saving $count references to cache");
        file_put_contents($cacheDir . "/references.json", json_encode($references, JSON_UNESCAPED_SLASHES));
    }
}
