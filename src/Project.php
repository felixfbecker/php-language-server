<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{SymbolInformation, TextDocumentIdentifier, ClientCapabilities};
use phpDocumentor\Reflection\DocBlockFactory;
use LanguageServer\ContentRetriever\ContentRetriever;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;

class Project
{
    /**
     * An associative array [string => PhpDocument]
     * that maps URIs to loaded PhpDocuments
     *
     * @var PhpDocument[]
     */
    private $documents = [];

    /**
     * Associative array from package identifier to index
     * The empty string represents the project itself
     *
     * @var Index[]
     */
    private $indexes = [];

    /**
     * An associative array that maps fully qualified symbol names to Definitions
     *
     * @var Definition[]
     */
    private $definitions = [];

    /**
     * An associative array that maps fully qualified symbol names to arrays of document URIs that reference the symbol
     *
     * @var PhpDocument[][]
     */
    private $references = [];

    /**
     * Instance of the PHP parser
     *
     * @var Parser
     */
    private $parser;

    /**
     * The DocBlockFactory instance to parse docblocks
     *
     * @var DocBlockFactory
     */
    private $docBlockFactory;

    /**
     * The DefinitionResolver instance to resolve reference nodes to Definitions
     *
     * @var DefinitionResolver
     */
    private $definitionResolver;

    /**
     * Reference to the language server client interface
     *
     * @var LanguageClient
     */
    private $client;

    /**
     * The content retriever
     *
     * @var ContentRetriever
     */
    private $contentRetriever;

    private $rootPath;

    private $composerLockFiles;

    /**
     * @param LanguageClient     $client             Used for logging and reporting diagnostics
     * @param ClientCapabilities $clientCapabilities Used for determining the right content/find strategies
     * @param string|null        $rootPath           Used for finding files in the project
     * @param string[]           $composerLockFiles  An array of URIs of composer.lock files in the project
     */
    public function __construct(
        LanguageClient $client,
        ClientCapabilities $clientCapabilities,
        array $composerLockFiles,
        string $rootPath = null
    ) {
        $this->client = $client;
        $this->rootPath = $rootPath;
        $this->parser = new Parser;
        $this->docBlockFactory = DocBlockFactory::createInstance();
        $this->definitionResolver = new DefinitionResolver($this);
        $this->contentRetriever = $contentRetriever;
        $this->composerLockFiles = $composerLockFiles;
        // The index for the project itself
        $this->indexes[''] = new Index;
    }

    /**
     * Returns the document indicated by uri.
     * Returns null if the document if not loaded.
     *
     * @param string $uri
     * @return PhpDocument|null
     */
    public function getDocument(string $uri)
    {
        return $this->documents[$uri] ?? null;
    }

    /**
     * Returns the document indicated by uri.
     * If the document is not open, loads it.
     *
     * @param string $uri
     * @return Promise <PhpDocument>
     */
    public function getOrLoadDocument(string $uri)
    {
        return isset($this->documents[$uri]) ? Promise\resolve($this->documents[$uri]) : $this->loadDocument($uri);
    }

    /**
     * Loads the document by doing a textDocument/xcontent request to the client.
     * If the client does not support textDocument/xcontent, tries to read the file from the file system.
     * The document is NOT added to the list of open documents, but definitions are registered.
     *
     * @param string $uri
     * @return Promise <PhpDocument>
     */
    public function loadDocument(string $uri): Promise
    {
        return coroutine(function () use ($uri) {

            $limit = 150000;
            $content = yield $this->contentRetriever->retrieve($uri);
            $size = strlen($content);
            if ($size > $limit) {
                throw new ContentTooLargeException($uri, $size, $limit);
            }

            /** The key for the index */
            $key = '';

            // If the document is part of a dependency
            if (preg_match($u['path'], '/vendor\/(\w+\/\w+)/', $matches)) {
                if ($this->composerLockFiles === null) {
                    throw new \Exception('composer.lock files were not read yet');
                }
                // Try to find closest composer.lock
                $u = Uri\parse($uri);
                $packageName = $matches[1];
                do {
                    $u['path'] = dirname($u['path']);
                    foreach ($this->composerLockFiles as $lockFileUri => $lockFileContent) {
                        $lockFileUri = Uri\parse($composerLockFile);
                        $lockFileUri['path'] = dirname($lockFileUri['path']);
                        if ($u == $lockFileUri) {
                            // Found it, find out package version
                            foreach ($lockFileContent->packages as $package) {
                                if ($package->name === $packageName) {
                                    $key = $packageName . ':' . $package->version;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                } while (!empty(trim($u, '/')));
            }

            // If there is no index for the key yet, create one
            if (!isset($this->indexes[$key])) {
                $this->indexes[$key] = new Index;
            }
            $index = $this->indexes[$key];

            if (isset($this->documents[$uri])) {
                $document = $this->documents[$uri];
                $document->updateContent($content);
            } else {
                $document = new PhpDocument(
                    $uri,
                    $content,
                    $this,
                    $index,
                    $this->client,
                    $this->parser,
                    $this->docBlockFactory,
                    $this->definitionResolver
                );
            }
            return $document;
        });
    }

    /**
     * Ensures a document is loaded and added to the list of open documents.
     *
     * @param string $uri
     * @param string $content
     * @return void
     */
    public function openDocument(string $uri, string $content)
    {
        if (isset($this->documents[$uri])) {
            $document = $this->documents[$uri];
            $document->updateContent($content);
        } else {
            $document = new PhpDocument(
                $uri,
                $content,
                $this,
                $this->client,
                $this->parser,
                $this->docBlockFactory,
                $this->definitionResolver
            );
            $this->documents[$uri] = $document;
        }
        return $document;
    }

    /**
     * Removes the document with the specified URI from the list of open documents
     *
     * @param string $uri
     * @return void
     */
    public function closeDocument(string $uri)
    {
        unset($this->documents[$uri]);
    }

    /**
     * Returns true if the document is open (and loaded)
     *
     * @param string $uri
     * @return bool
     */
    public function isDocumentOpen(string $uri): bool
    {
        return isset($this->documents[$uri]);
    }

    /**
     * Returns an associative array [string => Definition] that maps fully qualified symbol names
     * to Definitions
     *
     * @return Definitions[]
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * Returns the Definition object by a specific FQN
     *
     * @param string $fqn
     * @param bool $globalFallback Whether to fallback to global if the namespaced FQN was not found
     * @return Definition|null
     */
    public function getDefinition(string $fqn, $globalFallback = false)
    {
        if (isset($this->definitions[$fqn])) {
            return $this->definitions[$fqn];
        } else if ($globalFallback) {
            $parts = explode('\\', $fqn);
            $fqn = end($parts);
            return $this->getDefinition($fqn);
        }
    }

    /**
     * Registers a definition
     *
     * @param string $fqn The fully qualified name of the symbol
     * @param string $definition The Definition object
     * @return void
     */
    public function setDefinition(string $fqn, Definition $definition)
    {
        $this->definitions[$fqn] = $definition;
    }

    /**
     * Sets the Definition index
     *
     * @param Definition[] $definitions Map from FQN to Definition
     * @return void
     */
    public function setDefinitions(array $definitions)
    {
        $this->definitions = $definitions;
    }

    /**
     * Unsets the Definition for a specific symbol
     * and removes all references pointing to that symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return void
     */
    public function removeDefinition(string $fqn)
    {
        unset($this->definitions[$fqn]);
        unset($this->references[$fqn]);
    }

    /**
     * Adds a document URI as a referencee of a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return void
     */
    public function addReferenceUri(string $fqn, string $uri)
    {
        if (!isset($this->references[$fqn])) {
            $this->references[$fqn] = [];
        }
        // TODO: use DS\Set instead of searching array
        if (array_search($uri, $this->references[$fqn], true) === false) {
            $this->references[$fqn][] = $uri;
        }
    }

    /**
     * Removes a document URI as the container for a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @param string $uri The URI
     * @return void
     */
    public function removeReferenceUri(string $fqn, string $uri)
    {
        if (!isset($this->references[$fqn])) {
            return;
        }
        $index = array_search($fqn, $this->references[$fqn], true);
        if ($index === false) {
            return;
        }
        array_splice($this->references[$fqn], $index, 1);
    }

    /**
     * Returns all documents that reference a symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return Promise <PhpDocument[]>
     */
    public function getReferenceDocuments(string $fqn): Promise
    {
        if (!isset($this->references[$fqn])) {
            return Promise\resolve([]);
        }
        return Promise\all(array_map([$this, 'getOrLoadDocument'], $this->references[$fqn]));
    }

    /**
     * Returns an associative array [string => string[]] that maps fully qualified symbol names
     * to URIs of the document where the symbol is referenced
     *
     * @return string[][]
     */
    public function getReferenceUris()
    {
        return $this->references;
    }

    /**
     * Sets the reference index
     *
     * @param string[][] $references an associative array [string => string[]] from FQN to URIs
     * @return void
     */
    public function setReferenceUris(array $references)
    {
        $this->references = $references;
    }

    /**
     * Returns the document where a symbol is defined
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return Promise <PhpDocument|null>
     */
    public function getDefinitionDocument(string $fqn): Promise
    {
        if (!isset($this->definitions[$fqn])) {
            return Promise\resolve(null);
        }
        return $this->getOrLoadDocument($this->definitions[$fqn]->symbolInformation->location->uri);
    }

    /**
     * Returns true if the given FQN is defined in the project
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return bool
     */
    public function isDefined(string $fqn): bool
    {
        return isset($this->definitions[$fqn]);
    }
}
