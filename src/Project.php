<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{SymbolInformation, TextDocumentIdentifier, ClientCapabilities};
use phpDocumentor\Reflection\DocBlockFactory;
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
     * An associative array that maps fully qualified symbol names to SymbolInformations
     *
     * @var SymbolInformation[]
     */
    private $symbols = [];

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
     * Reference to the language server client interface
     *
     * @var LanguageClient
     */
    private $client;

    /**
     * The client's capabilities
     *
     * @var ClientCapabilities
     */
    private $clientCapabilities;

    public function __construct(LanguageClient $client, ClientCapabilities $clientCapabilities)
    {
        $this->client = $client;
        $this->clientCapabilities = $clientCapabilities;
        $this->parser = new Parser;
        $this->docBlockFactory = DocBlockFactory::createInstance();
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
            if ($this->clientCapabilities->xcontentProvider) {
                $content = (yield $this->client->textDocument->xcontent(new TextDocumentIdentifier($uri)))->text;
                $size = strlen($content);
                if ($size > $limit) {
                    throw new ContentTooLargeException($uri, $size, $limit);
                }
            } else {
                $path = uriToPath($uri);
                $size = filesize($path);
                if ($size > $limit) {
                    throw new ContentTooLargeException($uri, $size, $limit);
                }
                $content = file_get_contents($path);
            }
            if (isset($this->documents[$uri])) {
                $document = $this->documents[$uri];
                $document->updateContent($content);
            } else {
                $document = new PhpDocument($uri, $content, $this, $this->client, $this->parser, $this->docBlockFactory);
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
            $document = new PhpDocument($uri, $content, $this, $this->client, $this->parser, $this->docBlockFactory);
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
     * Returns an associative array [string => string] that maps fully qualified symbol names
     * to URIs of the document where the symbol is defined
     *
     * @return SymbolInformation[]
     */
    public function getSymbols()
    {
        return $this->symbols;
    }

    /**
     * Adds a SymbolInformation for a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @param string $uri The URI
     * @return void
     */
    public function setSymbol(string $fqn, SymbolInformation $symbol)
    {
        $this->symbols[$fqn] = $symbol;
    }

    /**
     * Sets the SymbolInformation index
     *
     * @param SymbolInformation[] $symbols
     * @return void
     */
    public function setSymbols(array $symbols)
    {
        $this->symbols = $symbols;
    }

    /**
     * Unsets the SymbolInformation for a specific symbol
     * and removes all references pointing to that symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return void
     */
    public function removeSymbol(string $fqn)
    {
        unset($this->symbols[$fqn]);
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
        if (!isset($this->symbols[$fqn])) {
            return Promise\resolve(null);
        }
        return $this->getOrLoadDocument($this->symbols[$fqn]->location->uri);
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
