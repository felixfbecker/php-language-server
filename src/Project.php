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
    private $documents;

    /**
     * An associative array [string => PhpDocument]
     * that maps fully qualified symbol names to loaded PhpDocuments
     *
     * @var PhpDocument[]
     */
    private $definitions;

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
     * Returns the document indicated by uri. Instantiates a new document if none exists.
     *
     * @param string $uri
     * @return LanguageServer\PhpDocument
     */
    public function getDocument(string $uri)
    {
        $uri = urldecode($uri);
        if (!isset($this->documents[$uri])) {
            $this->documents[$uri] = new PhpDocument($uri, $this, $this->client, $this->parser);
        }
        return $this->documents[$uri];
    }

    /**
     * Adds a document as the container for a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return void
     */
    public function addDefinitionDocument(string $fqn, PhpDocument $document)
    {
        $this->definitions[$fqn] = $document;
    }

    /**
     * Returns the document where a symbol is defined
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return PhpDocument|null
     */
    public function getDefinitionDocument(string $fqn)
    {
        return $this->definitions[$fqn] ?? null;
    }

    /**
     * Finds symbols in all documents, filtered by query parameter.
     *
     * @param string $query
     * @return SymbolInformation[]
     */
    public function findSymbols(string $query)
    {
        $queryResult = [];
        foreach ($this->documents as $uri => $document) {
            $queryResult = array_merge($queryResult, $document->findSymbols($query));
        }
        return $queryResult;
    }
}
