<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\NodeVisitor\ColumnCalculator;
use LanguageServer\NodeVisitor\DefinitionCollector;
use LanguageServer\NodeVisitor\DocBlockParser;
use LanguageServer\NodeVisitor\ReferencesAdder;
use LanguageServer\NodeVisitor\ReferencesCollector;
use LanguageServer\Protocol\ClientCapabilities;
use LanguageServer\Protocol\Diagnostic;
use LanguageServer\Protocol\DiagnosticSeverity;
use phpDocumentor\Reflection\DocBlockFactory;
use PhpParser\ErrorHandler\Collecting;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitor\NameResolver;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;

class PhpDocumentFactory
{
    /**
     * Holds account of all definitions, references etc.
     * @var Project
     */
    private $project;
    
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

    public function __construct(LanguageClient $client, Project $project, ClientCapabilities $clientCapabilities = null)
    {
        $this->client = $client;
        $this->clientCapabilities = $clientCapabilities ? : new ClientCapabilities();
        $this->parser = new Parser;
        $this->docBlockFactory = DocBlockFactory::createInstance();
        $this->definitionResolver = new DefinitionResolver($project);
        $this->project = $project;
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
            
            return $this->createDocument($uri, $content, false);
        });
    }

    /**
     * Ensures a document is loaded and added to the list of open documents.
     *
     * @param string  $uri
     * @param string  $content
     * @param boolean $index
     * @return void
     */
    public function createDocument(string $uri, string $content, bool $index = true)
    {
        if ($this->project->getDocument($uri)) {
            $document = $this->project->getDocument($uri);
        } else {
            $document = new PhpDocument($uri, $this->project);
            if ($index)
                $this->project->addDocument($document);
        }
        
        $this->handleContent($document, $content);
        return $document;
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
    private function handleContent(PhpDocument $document, string $content)
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
                $diagnostics[] = Diagnostic::fromError($error, $content, DiagnosticSeverity::WARNING, 'php');
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
            if ($document->getDefinitions() !== null) {
                foreach ($document->getDefinitions() as $fqn => $definition) {
                    $this->project->removeDefinition($fqn);
                }
            }
            // Register this document on the project for all the symbols defined in it
            $document->setDefinitions($definitionCollector->definitions);
            $document->setDefinitionNodes($definitionCollector->nodes);
            foreach ($definitionCollector->definitions as $fqn => $definition) {
                $this->project->setDefinition($fqn, $definition);
            }

            // Unregister old references
            if ($document->hasReferenceNodes()) {
                foreach ($document->getReferenceNodes() as $fqn => $node) {
                    $this->project->removeReferenceUri($fqn, $document->getUri());
                }
            }
            // Register this document on the project for references
            $document->setReferenceNodes($referencesCollector->nodes);
            foreach ($referencesCollector->nodes as $fqn => $nodes) {
                $this->project->addReferenceUri($fqn, $document->getUri());
            }
        } 
        
        $document->updateContent($content, $stmts);

        if (!$document->isVendored()) {
            $this->client->textDocument->publishDiagnostics($document->getUri(), $diagnostics);
        }
    }
}
