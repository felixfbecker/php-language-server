<?php
declare(strict_types=1);

namespace LanguageServer\Server;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use Amp\Promise;
use Amp\Success;
use LanguageServer\{LanguageClient, PhpDocumentLoader};
use LanguageServer\Factory\LocationFactory;
use LanguageServer\Index\{DependenciesIndex, Index, ProjectIndex};
use LanguageServerProtocol\{DependencyReference, FileChangeType, FileEvent, ReferenceInformation, SymbolDescriptor};

/**
 * Provides method handlers for all workspace/* methods
 */
class Workspace
{
    /**
     * @var LanguageClient
     */
    public $client;

    /**
     * The symbol index for the workspace
     *
     * @var ProjectIndex
     */
    private $projectIndex;

    /**
     * @var DependenciesIndex
     */
    private $dependenciesIndex;

    /**
     * @var Index
     */
    private $sourceIndex;

    /**
     * @var \stdClass
     */
    public $composerLock;

    /**
     * @var PhpDocumentLoader
     */
    public $documentLoader;

    /**
     * @param LanguageClient $client LanguageClient instance used to signal updated results
     * @param ProjectIndex $projectIndex Index that is used to wait for full index completeness
     * @param DependenciesIndex $dependenciesIndex Index that is used on a workspace/xreferences request
     * @param DependenciesIndex $sourceIndex Index that is used on a workspace/xreferences request
     * @param \stdClass $composerLock The parsed composer.lock of the project, if any
     * @param PhpDocumentLoader $documentLoader PhpDocumentLoader instance to load documents
     */
    public function __construct(LanguageClient $client, ProjectIndex $projectIndex, DependenciesIndex $dependenciesIndex, Index $sourceIndex, \stdClass $composerLock = null, PhpDocumentLoader $documentLoader, \stdClass $composerJson = null)
    {
        $this->client = $client;
        $this->sourceIndex = $sourceIndex;
        $this->projectIndex = $projectIndex;
        $this->dependenciesIndex = $dependenciesIndex;
        $this->composerLock = $composerLock;
        $this->documentLoader = $documentLoader;
        $this->composerJson = $composerJson;
    }

    /**
     * The workspace symbol request is sent from the client to the server to list project-wide symbols matching the query string.
     *
     * @param string $query
     * @return Promise <SymbolInformation[]>
     */
    public function symbol(string $query): Promise
    {
        $symbols = [];
        foreach ($this->sourceIndex->getDefinitions() as $fqn => $definition) {
            if ($query === '' || stripos($fqn, $query) !== false) {
                $symbols[] = $definition->symbolInformation;
            }
        }
        return new Success($symbols);
    }

    /**
     * The watched files notification is sent from the client to the server when the client detects changes to files watched by the language client.
     *
     * @param FileEvent[] $changes
     * @return void
     */
    public function didChangeWatchedFiles(array $changes)
    {
        Loop::defer(function () use ($changes) {
            foreach ($changes as $change) {
                if ($change->type === FileChangeType::DELETED) {
                    yield from $this->client->textDocument->publishDiagnostics($change->uri, []);
                }
            }
        });
    }

    /**
     * The workspace references request is sent from the client to the server to locate project-wide references to a symbol given its description / metadata.
     *
     * @param SymbolDescriptor $query Partial metadata about the symbol that is being searched for.
     * @param string[] $files An optional list of files to restrict the search to.
     * @return \Generator
     * @throws \LanguageServer\ContentTooLargeException
     */
    public function xreferences($query, array $files = null): \Generator
    {
        $deferred = new Deferred();
        Loop::defer(function () use ($deferred, $query, $files) {
            // TODO: $files is unused in the coroutine
            if ($this->composerLock === null) {
                return [];
            }
            /** Map from URI to array of referenced FQNs in dependencies */
            $refs = [];
            // Get all references TO dependencies
            $fqns = isset($query->fqsen) ? [$query->fqsen] : array_values(yield from $this->dependenciesIndex->getDefinitions());
            foreach ($fqns as $fqn) {
                foreach ($this->sourceIndex->getReferenceUris($fqn) as $uri) {
                    if (!isset($refs[$uri])) {
                        $refs[$uri] = [];
                    }
                    if (array_search($uri, $refs[$uri]) === false) {
                        $refs[$uri][] = $fqn;
                    }
                }
            }
            $refInfos = [];
            foreach ($refs as $uri => $fqns) {
                foreach ($fqns as $fqn) {
                    $doc = yield from $this->documentLoader->getOrLoad($uri);
                    foreach ($doc->getReferenceNodesByFqn($fqn) as $node) {
                        $refInfo = new ReferenceInformation;
                        $refInfo->reference = LocationFactory::fromNode($node);
                        $refInfo->symbol = $query;
                        $refInfos[] = $refInfo;
                    }
                }
            }
            $deferred->resolve($refInfos);
        });
        return $deferred->promise();
    }

    /**
     * @return DependencyReference[]
     */
    public function xdependencies(): array
    {
        if ($this->composerLock === null) {
            return [];
        }
        $dependencyReferences = [];
        foreach (array_merge($this->composerLock->packages, (array)$this->composerLock->{'packages-dev'}) as $package) {
            $dependencyReferences[] = new DependencyReference($package);
        }
        return $dependencyReferences;
    }
}
