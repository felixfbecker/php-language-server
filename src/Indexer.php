<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Cache\Cache;
use LanguageServer\FilesFinder\FilesFinder;
use LanguageServer\Index\{DependenciesIndex, Index};
use LanguageServerProtocol\MessageType;
use Webmozart\PathUtil\Path;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;

class Indexer
{
    /**
     * @var int The prefix for every cache item
     */
    const CACHE_VERSION = 3;

    /**
     * @var FilesFinder
     */
    private $filesFinder;

    /**
     * @var string
     */
    private $rootPath;

    /**
     * @var LanguageClient
     */
    private $client;

    /**
     * @var Cache
     */
    private $cache;

    /**
     * @var DependenciesIndex
     */
    private $dependenciesIndex;

    /**
     * @var Index
     */
    private $sourceIndex;

    /**
     * @var PhpDocumentLoader
     */
    private $documentLoader;

    /**
     * @var \stdClasss
     */
    private $composerLock;

    /**
     * @var \stdClasss
     */
    private $composerJson;

    /**
     * @var bool
     */
    private $supportsWorkDoneProgress;

    /**
     * @var Client\WorkDoneProgress
     */
    private $workDoneProgress;

    /**
     * @param FilesFinder       $filesFinder
     * @param string            $rootPath
     * @param LanguageClient    $client
     * @param Cache             $cache
     * @param DependenciesIndex $dependenciesIndex
     * @param Index             $sourceIndex
     * @param PhpDocumentLoader $documentLoader
     * @param \stdClass|null    $composerLock
     * @param bool              $supportsWorkDoneProgress
     */
    public function __construct(
        FilesFinder $filesFinder,
        string $rootPath,
        LanguageClient $client,
        Cache $cache,
        DependenciesIndex $dependenciesIndex,
        Index $sourceIndex,
        PhpDocumentLoader $documentLoader,
        \stdClass $composerLock = null,
        \stdClass $composerJson = null,
        bool $supportsWorkDoneProgress = false
    ) {
        $this->filesFinder = $filesFinder;
        $this->rootPath = $rootPath;
        $this->client = $client;
        $this->cache = $cache;
        $this->dependenciesIndex = $dependenciesIndex;
        $this->sourceIndex = $sourceIndex;
        $this->documentLoader = $documentLoader;
        $this->composerLock = $composerLock;
        $this->composerJson = $composerJson;
        $this->supportsWorkDoneProgress = $supportsWorkDoneProgress;
    }

    /**
     * Will read and parse the passed source files in the project and add them to the appropiate indexes
     *
     * @return Promise <void>
     */
    public function index(): Promise
    {
        return coroutine(function () {

            //$this->workDoneProgress = $this->supportsWorkDoneProgress ? yield $this->client->window->createWorkDoneProgress() : null;

            $pattern = Path::makeAbsolute('**/*.php', $this->rootPath);
            $uris = yield $this->filesFinder->find($pattern);

            $count = count($uris);
            $startTime = microtime(true);
            $this->client->window->logMessage(MessageType::INFO, "$count files total");

            //if ($this->workDoneProgress) {
            //    $this->workDoneProgress->beginProgress('Indexing', "0/$count files", 0);
            //}

            /** @var string[] */
            $source = [];
            /** @var string[][] */
            $deps = [];

            foreach ($uris as $uri) {
                $packageName = getPackageName($uri, $this->composerJson);
                if ($this->composerLock !== null && $packageName) {
                    // Dependency file
                    if (!isset($deps[$packageName])) {
                        $deps[$packageName] = [];
                    }
                    $deps[$packageName][] = $uri;
                } else {
                    // Source file
                    $source[] = $uri;
                }
            }

            // Index source
            // Definitions and static references
            $this->client->window->logMessage(MessageType::INFO, 'Indexing project for definitions and static references');
            yield $this->indexFiles($source, 'Indexing project for definitions and static references');
            $this->sourceIndex->setStaticComplete();
            // Dynamic references
            $this->client->window->logMessage(MessageType::INFO, 'Indexing project for dynamic references');
            yield $this->indexFiles($source, 'Indexing project for dynamic references');
            $this->sourceIndex->setComplete();

            // Index dependencies
            $this->client->window->logMessage(MessageType::INFO, count($deps) . ' Packages');
            foreach ($deps as $packageName => $files) {
                // Find version of package and check cache
                $packageKey = null;
                $cacheKey = null;
                $index = null;
                foreach (array_merge($this->composerLock->packages, (array)$this->composerLock->{'packages-dev'}) as $package) {
                    // Check if package name matches and version is absolute
                    // Dynamic constraints are not cached, because they can change every time
                    $packageVersion = ltrim($package->version, 'v');
                    if ($package->name === $packageName && strpos($packageVersion, 'dev') === false) {
                        $packageKey = $packageName . ':' . $packageVersion;
                        $cacheKey = self::CACHE_VERSION . ':' . $packageKey;
                        // Check cache
                        $index = yield $this->cache->get($cacheKey);
                        break;
                    }
                }
                if ($index !== null) {
                    // Cache hit
                    $this->dependenciesIndex->setDependencyIndex($packageName, $index);
                    $this->client->window->logMessage(MessageType::INFO, "Restored $packageKey from cache");
                } else {
                    // Cache miss
                    $index = $this->dependenciesIndex->getDependencyIndex($packageName);

                    // Index definitions and static references
                    $this->client->window->logMessage(MessageType::INFO, 'Indexing ' . ($packageKey ?? $packageName) . ' for definitions and static references');
                    yield $this->indexFiles($files, 'Indexing ' . ($packageKey ?? $packageName) . ' for definitions and static references');
                    $index->setStaticComplete();

                    // Index dynamic references
                    $this->client->window->logMessage(MessageType::INFO, 'Indexing ' . ($packageKey ?? $packageName) . ' for dynamic references');
                    yield $this->indexFiles($files, 'Indexing ' . ($packageKey ?? $packageName) . ' for dynamic references');
                    $index->setComplete();

                    // If we know the version (cache key), save index for the dependency in the cache
                    if ($cacheKey !== null) {
                        $this->client->window->logMessage(MessageType::INFO, "Storing $packageKey in cache");
                        $this->cache->set($cacheKey, $index);
                    } else {
                        $this->client->window->logMessage(MessageType::WARNING, "Could not compute cache key for $packageName");
                    }
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
     * @param array $files
     * @return Promise
     */
    private function indexFiles(array $files, string $progressTitle): Promise
    {
        return coroutine(function () use ($files, $progressTitle) {
            $workDoneProgress = null;
            if ($this->supportsWorkDoneProgress && $workDoneProgress = yield $this->client->window->createWorkDoneProgress()) {
                $workDoneProgress->beginProgress($progressTitle, "0/".count($files)." files", 0);
            }

            foreach ($files as $i => $uri) {
                if ($workDoneProgress) {
                    $workDoneProgress->reportProgress("$i/".count($files)." files", intval($i/count($files)*100));
                }
                // Skip open documents
                if ($this->documentLoader->isOpen($uri)) {
                    continue;
                }

                // Give LS to the chance to handle requests while indexing
                yield timeout();
                $this->client->window->logMessage(MessageType::LOG, "Parsing $uri");
                try {
                    $document = yield $this->documentLoader->load($uri);
                    if (!isVendored($document, $this->composerJson)) {
                        $this->client->textDocument->publishDiagnostics($uri, $document->getDiagnostics());
                    }
                } catch (ContentTooLargeException $e) {
                    $this->client->window->logMessage(
                        MessageType::INFO,
                        "Ignoring file {$uri} because it exceeds size limit of {$e->limit} bytes ({$e->size})"
                    );
                } catch (\Exception $e) {
                    $this->client->window->logMessage(MessageType::ERROR, "Error parsing $uri: " . (string)$e);
                }
            }
            if ($workDoneProgress) {
                $workDoneProgress->endProgress();
            }
        });
    }
}
