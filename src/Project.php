<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer};
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\NodeVisitor\NameResolver;

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
     * An associative array that maps fully qualified symbol names to document URIs
     *
     * @var string[]
     */
    private $definitions = [];

    /**
     * Instance of the PHP parser
     *
     * @var ParserAbstract
     */
    private $parser;

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
            $document = new PhpDocument($uri, $content, $this, $this->client, $this->parser);
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
            $document = new PhpDocument($uri, $content, $this, $this->client, $this->parser);
            $this->documents[$uri] = $document;
        }
        return $document;
    }

    public function closeDocument(string $uri)
    {
        unset($this->documents[$uri]);
    }

    public function isDocumentOpen(string $uri)
    {
        return isset($this->documents[$uri]);
    }

    /**
     * Adds a document as the container for a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return void
     */
    public function addDefinitionDocument(string $fqn, string $uri)
    {
        $this->definitions[$fqn] = $uri;
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
     * Returns an associative array [string => string] that maps fully qualified symbol names
     * to URIs of the document where the symbol is defined
     *
     * @return PhpDocument[]
     */
    public function &getDefinitionDocuments()
    {
        return $this->definitions;
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
