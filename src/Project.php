<?php
declare(strict_types = 1);

namespace LanguageServer;

use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\{ParserFactory, Lexer};

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
     * An associative array that maps fully qualified symbol names to document URIs that define the symbol
     *
     * @var string[]
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
     * @var ParserAbstract
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

    public function __construct(LanguageClient $client)
    {
        $this->client = $client;

        $lexer = new Lexer(['usedAttributes' => ['comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos']]);
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer, ['throwOnError' => false]);
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * Returns the document indicated by uri.
     * If the document is not open, tries to read it from disk, but the document is not added the list of open documents.
     *
     * @param string $uri
     * @return LanguageServer\PhpDocument
     */
    public function getDocument(string $uri)
    {
        if (!isset($this->documents[$uri])) {
            return $this->loadDocument($uri);
        } else {
            return $this->documents[$uri];
        }
    }

    /**
     * Reads a document from disk.
     * The document is NOT added to the list of open documents, but definitions are registered.
     *
     * @param string $uri
     * @return LanguageServer\PhpDocument
     */
    public function loadDocument(string $uri)
    {
        $content = file_get_contents(uriToPath($uri));
        if (isset($this->documents[$uri])) {
            $document = $this->documents[$uri];
            $document->updateContent($content);
        } else {
            $document = new PhpDocument($uri, $content, $this, $this->client, $this->parser, $this->docBlockFactory);
        }
        return $document;
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
     * @return PhpDocument[]
     */
    public function getDefinitionUris()
    {
        return $this->definitions;
    }

    /**
     * Adds a document URI as the container for a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @param string $uri The URI
     * @return void
     */
    public function setDefinitionUri(string $fqn, string $uri)
    {
        $this->definitions[$fqn] = $uri;
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
     * Returns all documents that reference a symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return PhpDocument[]
     */
    public function getReferenceDocuments(string $fqn)
    {
        if (!isset($this->references[$fqn])) {
            return [];
        }
        return array_map([$this, 'getDocument'], $this->references[$fqn]);
    }

    /**
     * Returns the document where a symbol is defined
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return PhpDocument|null
     */
    public function getDefinitionDocument(string $fqn)
    {
        return isset($this->definitions[$fqn]) ? $this->getDocument($this->definitions[$fqn]) : null;
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
