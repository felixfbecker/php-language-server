<?php
declare(strict_types = 1);

namespace LanguageServer\Server;

use LanguageServer\{LanguageClient, Project, PhpDocumentLoader};
use LanguageServer\Index\{ProjectIndex, DependenciesIndex, Index};
use LanguageServer\Protocol\{SymbolInformation, SymbolDescriptor, ReferenceInformation, DependencyReference, Location};
use Sabre\Event\Promise;
use Rx\Observable;
use function Sabre\Event\coroutine;
use function LanguageServer\waitForEvent;

/**
 * Provides method handlers for all workspace/* methods
 */
class Workspace
{
    /**
     * The symbol index for the workspace
     *
     * @var ProjectIndex
     */
    private $index;

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
     * @param ProjectIndex      $index             Index that is searched on a workspace/symbol request
     * @param DependenciesIndex $dependenciesIndex Index that is used on a workspace/xreferences request
     * @param DependenciesIndex $sourceIndex       Index that is used on a workspace/xreferences request
     * @param \stdClass         $composerLock      The parsed composer.lock of the project, if any
     * @param PhpDocumentLoader $documentLoader    PhpDocumentLoader instance to load documents
     */
    public function __construct(ProjectIndex $index, DependenciesIndex $dependenciesIndex, Index $sourceIndex, \stdClass $composerLock = null, PhpDocumentLoader $documentLoader)
    {
        $this->sourceIndex = $sourceIndex;
        $this->index = $index;
        $this->dependenciesIndex = $dependenciesIndex;
        $this->composerLock = $composerLock;
        $this->documentLoader = $documentLoader;
    }

    /**
     * The workspace symbol request is sent from the client to the server to list project-wide symbols matching the query string.
     *
     * @param string $query
     * @return Observable Will yield JSON Patch Operations that eventually result in SymbolInformation[]
     */
    public function symbol(string $query): Observable
    {
        return Observable::just(null)
            // Wait for indexing event if not yet finished
            ->flatMap(function () {
                if (!$this->index->isStaticComplete()) {
                    return observableFromEvent($this->index, 'static-complete')->take(1);
                }
            })
            // Get definitions from complete index
            ->flatMap(function () {
                return Observable::fromArray($this->index->getDefinitions());
            })
            // Filter by matching FQN to query
            ->filter(function (Definition $def) use ($query) {
                return $query === '' || stripos($def->fqn, $query) !== false;
            })
            // Send each SymbolInformation
            ->map(function (Definition $def) use ($query) {
                return new Operation\Add('/-', $def->symbolInformation);
            })
            // Initialize with an empty array
            ->startWith(new Operation\Replace('/', []));
    }

    /**
     * The workspace references request is sent from the client to the server to locate project-wide references to a symbol given its description / metadata.
     *
     * @param SymbolDescriptor $query Partial metadata about the symbol that is being searched for.
     * @param string[]         $files An optional list of files to restrict the search to.
     * @return Observable ReferenceInformation[]
     */
    public function xreferences($query, array $files = null): Observable
    {
        return Observable::just(null)
            ->flatMap(function () {
                if ($this->composerLock === null) {
                    return Observable::empty();
                }
                // Wait until indexing finished
                if (!$this->index->isComplete()) {
                    return observableFromEvent($this->index, 'complete')->take(1);
                }
            })
            // Get all definition FQNs in dependencies
            ->flatMap(function () {
                if (isset($query->fqsen)) {
                    $fqns = [$this->dependenciesIndex->getDefinition($query->fqsen)];
                } else {
                    $fqns = $this->dependenciesIndex->getDefinitions();
                }
                return Observable::fromArray($fqns);
            })
            // Get all URIs in the project source that reference those definitions
            ->flatMap(function (Definition $def) {
                return Observable::fromArray($this->sourceIndex->getReferenceUris($fqn));
            })
            ->distinct()
            ->flatMap(function (string $uri) {
                return $this->documentLoader->getOrLoad($uri);
                $symbol = new SymbolDescriptor;
                $symbol->fqsen = $fqn;
                foreach (get_object_vars($def->symbolInformation) as $prop => $val) {
                    $symbol->$prop = $val;
                }
                // Find out package name
                preg_match('/\/vendor\/([^\/]+\/[^\/]+)\//', $def->symbolInformation->location->uri, $matches);
                $packageName = $matches[1];
                foreach (array_merge($this->composerLock->packages, $this->composerLock->{'packages-dev'}) as $package) {
                    if ($package->name === $packageName) {
                        $symbol->package = $package;
                        break;
                    }
                }
            })
                // If there was no FQSEN provided, check if query attributes match
                if (!isset($query->fqsen)) {
                    $matches = true;
                    foreach (get_object_vars($query) as $prop => $val) {
                        if ($query->$prop != $symbol->$prop) {
                            $matches = false;
                            break;
                        }
                    }
                    if (!$matches) {
                        continue;
                    }
                }
                foreach ($doc->getReferenceNodesByFqn($fqn) as $node) {
                    $refInfo = new ReferenceInformation;
                    $refInfo->reference = Location::fromNode($node);
                    $refInfo->symbol = $symbol;
                    $refInfos[] = $refInfo;
                }
            })
            ->flatMap(function (PhpDocument $doc) use ($fqn) {

            })
            $refInfos = [];
            foreach ($refs as $uri => $fqns) {
                foreach ($fqns as $fqn) {
                }
            }
            return $refInfos;
        })
            ->startWith(new Operation\Replace('/', []));
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
        foreach ($this->composerLock->packages as $package) {
            $dependencyReferences[] = new DependencyReference($package);
        }
        return $dependencyReferences;
    }
}
