<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\NodeVisitor\ColumnCalculator;
use LanguageServer\NodeVisitor\DefinitionCollector;
use LanguageServer\NodeVisitor\DocBlockParser;
use LanguageServer\NodeVisitor\ReferencesAdder;
use LanguageServer\NodeVisitor\ReferencesCollector;
use LanguageServer\NodeVisitor\VariableReferencesCollector;
use LanguageServer\Protocol\ClientCapabilities;
use LanguageServer\Protocol\Diagnostic;
use LanguageServer\Protocol\DiagnosticSeverity;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\Node;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\ClosureUse;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\FunctionLike;
use PhpParser\Node\Param;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
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
        $this->definitionResolver = new DefinitionResolver($this);
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
                $content = (yield $this->client->textDocument->xcontent(new Protocol\TextDocumentIdentifier($uri)))->text;
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
            } else {
                $document = new PhpDocument($uri, $content);
            }
            $this->updateContent($document, $content);
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
        } else {
            $document = new PhpDocument($uri, $content);
            $this->documents[$uri] = $document;
        }
        $this->updateContent($document, $content);
        
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
    
    /**
     * Returns the reference nodes for any node
     * The references node MAY be in other documents, check the ownerDocument attribute
     *
     * @param Node $node
     * @return Promise <Node[]>
     */
    public function getReferenceNodesByNode(Node $node): Promise
    {
        return coroutine(function () use ($node) {
            // Variables always stay in the boundary of the file and need to be searched inside their function scope
            // by traversing the AST
            if (
                $node instanceof Variable
                || $node instanceof Param
                || $node instanceof ClosureUse
            ) {
                if ($node->name instanceof Expr) {
                    return null;
                }
                // Find function/method/closure scope
                $n = $node;
                while (isset($n) && !($n instanceof FunctionLike)) {
                    $n = $n->getAttribute('parentNode');
                }
                if (!isset($n)) {
                    $n = $node->getAttribute('ownerDocument');
                }
                $traverser = new NodeTraverser;
                $refCollector = new VariableReferencesCollector($node->name);
                $traverser->addVisitor($refCollector);
                $traverser->traverse($n->getStmts());
                return $refCollector->nodes;
            }
            // Definition with a global FQN
            $fqn = DefinitionResolver::getDefinedFqn($node);
            if ($fqn === null) {
                return [];
            }
            $refDocuments = yield $this->getReferenceDocuments($fqn);
            $nodes = [];
            foreach ($refDocuments as $document) {
                $refs = $document->getReferenceNodesByFqn($fqn);
                if ($refs !== null) {
                    foreach ($refs as $ref) {
                        $nodes[] = $ref;
                    }
                }
            }
            return $nodes;
        });
    }
    
    /**
     * Updates the content on this document.
     * Re-parses a source file, updates symbols and reports parsing errors
     * that may have occured as diagnostics.
     *
     * @param PhpDocument $document
     * @param string $content
     * @return void
     */
    public function updateContent(PhpDocument $document, string $content)
    {
        $errorHandler = new Collecting;
        $stmts = $this->parser->parse($content, $errorHandler);

        $diagnostics = [];
        foreach ($errorHandler->getErrors() as $error) {
            $diagnostics[] = Diagnostic::fromError($error, $content, DiagnosticSeverity::ERROR, 'php');
        }

        // $stmts can be null in case of a fatal parsing error
        if ($stmts) {
            $traverser = new NodeTraverser;

            // Resolve aliased names to FQNs
            $traverser->addVisitor(new NameResolver($errorHandler));

            // Add parentNode, previousSibling, nextSibling attributes
            $traverser->addVisitor(new ReferencesAdder($document));

            // Add column attributes to nodes
            $traverser->addVisitor(new ColumnCalculator($content));

            // Parse docblocks and add docBlock attributes to nodes
            $docBlockParser = new DocBlockParser($this->docBlockFactory);
            $traverser->addVisitor($docBlockParser);

            $traverser->traverse($stmts);

            // Report errors from parsing docblocks
            foreach ($docBlockParser->errors as $error) {
                $diagnostics[] = Diagnostic::fromError($error, $document->content, DiagnosticSeverity::WARNING, 'php');
            }

            $traverser = new NodeTraverser;

            // Collect all definitions
            $definitionCollector = new DefinitionCollector($this->definitionResolver);
            $traverser->addVisitor($definitionCollector);

            // Collect all references
            $referencesCollector = new ReferencesCollector($this->definitionResolver);
            $traverser->addVisitor($referencesCollector);

            $traverser->traverse($stmts);

            // Unregister old definitions
            foreach ($document->getDefinitions() as $fqn => $definition) {
                $this->removeDefinition($fqn);
            }
            
            // Register this document on the project for all the symbols defined in it
            $document->setDefinitions($definitionCollector->definitions);
            $document->setDefinitionNodes($definitionCollector->nodes);
            foreach ($definitionCollector->definitions as $fqn => $definition) {
                $this->setDefinition($fqn, $definition);
            }

            // Unregister old references
            foreach ($document->getReferenceNodes() as $fqn => $node) {
                $this->removeReferenceUri($fqn, $document->uri);
            }
            
            // Register this document on the project for references
            $document->setReferenceNodes($referencesCollector->nodes);
            foreach ($referencesCollector->nodes as $fqn => $nodes) {
                $this->addReferenceUri($fqn, $document->getUri());
            }

        }
        
        $document->updateContent($content, $stmts);

        if (!$document->isVendored()) {
            $this->client->textDocument->publishDiagnostics($document->getUri(), $diagnostics);
        }
    }
}
