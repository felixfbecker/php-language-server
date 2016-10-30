<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Position, TextEdit};
use LanguageServer\NodeVisitor\{
    NodeAtPositionFinder,
    ReferencesAdder,
    DocBlockParser,
    DefinitionCollector,
    ColumnCalculator,
    ReferencesCollector,
    VariableReferencesCollector
};
use PhpParser\{Error, ErrorHandler, Node, NodeTraverser};
use PhpParser\NodeVisitor\NameResolver;
use phpDocumentor\Reflection\DocBlockFactory;
use function LanguageServer\Fqn\{getDefinedFqn, getVariableDefinition, getReferencedFqn};
use LanguageServer\Completion\CompletionReporter;

class PhpDocument
{
    /**
     * The LanguageClient instance (to report errors etc)
     *
     * @var LanguageClient
     */
    private $client;

    /**
     * The Project this document belongs to (to register definitions etc)
     *
     * @var Project
     */
    public $project;
    // for whatever reason I get "cannot access private property" error if $project is not public
    // https://github.com/felixfbecker/php-language-server/pull/49#issuecomment-252427359

    /**
     * The PHPParser instance
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
     * The URI of the document
     *
     * @var string
     */
    private $uri;

    /**
     * The content of the document
     *
     * @var string
     */
    private $content;

    /**
     * The AST of the document
     *
     * @var Node[]
     */
    private $stmts;

    /**
     * Map from fully qualified name (FQN) to Node
     *
     * @var Node[]
     */
    private $definitions;

    /**
     * Map from fully qualified name (FQN) to array of nodes that reference the symbol
     *
     * @var Node[][]
     */
    private $references;

    /**
     * Map from fully qualified name (FQN) to SymbolInformation
     *
     * @var SymbolInformation[]
     */
    private $symbols;

    /**
     *
     * @var \LanguageServer\Completion\CompletionReporter
     */
    private $completionReporter;

    /**
     * @param string          $uri             The URI of the document
     * @param string          $content         The content of the document
     * @param Project         $project         The Project this document belongs to (to register definitions etc)
     * @param LanguageClient  $client          The LanguageClient instance (to report errors etc)
     * @param Parser          $parser          The PHPParser instance
     * @param DocBlockFactory $docBlockFactory The DocBlockFactory instance to parse docblocks
     */
    public function __construct(string $uri, string $content, Project $project, LanguageClient $client, Parser $parser, DocBlockFactory $docBlockFactory)
    {
        $this->uri = $uri;
        $this->project = $project;
        $this->client = $client;
        $this->parser = $parser;
        $this->docBlockFactory = $docBlockFactory;
        $this->updateContent($content);
    }

    /**
     * Get all references of a fully qualified name
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return Node[]
     */
    public function getReferencesByFqn(string $fqn)
    {
        return isset($this->references) && isset($this->references[$fqn]) ? $this->references[$fqn] : null;
    }

    /**
     * Updates the content on this document.
     * Re-parses a source file, updates symbols and reports parsing errors
     * that may have occured as diagnostics.
     *
     * @param string $content
     * @return void
     */
    public function updateContent(string $content)
    {
        $this->content = $content;
        $this->completionReporter = new CompletionReporter($this);

        $stmts = null;

        $errorHandler = new ErrorHandler\Collecting;
        $stmts = $this->parser->parse($content, $errorHandler);

        $diagnostics = [];
        foreach ($errorHandler->getErrors() as $error) {
            $diagnostics[] = Diagnostic::fromError($error, $this->content, DiagnosticSeverity::ERROR, 'php');
        }

        // $stmts can be null in case of a fatal parsing error
        if ($stmts) {
            $traverser = new NodeTraverser;

            // Resolve aliased names to FQNs
            $traverser->addVisitor(new NameResolver($errorHandler));

            // Add parentNode, previousSibling, nextSibling attributes
            $traverser->addVisitor(new ReferencesAdder($this));

            // Add column attributes to nodes
            $traverser->addVisitor(new ColumnCalculator($content));

            // Parse docblocks and add docBlock attributes to nodes
            $docBlockParser = new DocBlockParser($this->docBlockFactory);
            $traverser->addVisitor($docBlockParser);

            $traverser->traverse($stmts);

            // Report errors from parsing docblocks
            foreach ($docBlockParser->errors as $error) {
                $diagnostics[] = Diagnostic::fromError($error, $this->content, DiagnosticSeverity::WARNING, 'php');
            }

            $traverser = new NodeTraverser;

            // Collect all definitions
            $definitionCollector = new DefinitionCollector;
            $traverser->addVisitor($definitionCollector);

            // Collect all references
            $referencesCollector = new ReferencesCollector;
            $traverser->addVisitor($referencesCollector);

            $traverser->traverse($stmts);

            // Unregister old definitions
            if (isset($this->definitions)) {
                foreach ($this->definitions as $fqn => $node) {
                    $this->project->removeSymbol($fqn);
                }
            }
            // Register this document on the project for all the symbols defined in it
            $this->definitions = $definitionCollector->definitions;
            $this->symbols = $definitionCollector->symbols;
            foreach ($definitionCollector->symbols as $fqn => $symbol) {
                $this->project->setSymbol($fqn, $symbol);
            }

            // Unregister old references
            if (isset($this->references)) {
                foreach ($this->references as $fqn => $node) {
                    $this->project->removeReferenceUri($fqn, $this->uri);
                }
            }
            // Register this document on the project for references
            $this->references = $referencesCollector->references;
            foreach ($referencesCollector->references as $fqn => $nodes) {
                $this->project->addReferenceUri($fqn, $this->uri);
            }

            $this->stmts = $stmts;
        }

        $this->client->textDocument->publishDiagnostics($this->uri, $diagnostics);
    }

    /**
     * Returns array of TextEdit changes to format this document.
     *
     * @return \LanguageServer\Protocol\TextEdit[]
     */
    public function getFormattedText()
    {
        if (empty($this->content)) {
            return [];
        }
        return Formatter::format($this->content, $this->uri);
    }

    /**
     * @param Position $position
     *
     * @return \LanguageServer\Protocol\CompletionList
     */
    public function complete(Position $position)
    {
        $this->completionReporter->complete($position);
        return $this->completionReporter->getCompletionList();
    }

    /**
     * Returns this document's text content.
     *
     * @return string
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * Returns the URI of the document
     *
     * @return string
     */
    public function getUri(): string
    {
        return $this->uri;
    }

    /**
     * Returns the AST of the document
     *
     * @return Node[]
     */
    public function getStmts(): array
    {
        return $this->stmts;
    }

    /**
     * Returns the node at a specified position
     *
     * @param Position $position
     * @return Node|null
     */
    public function getNodeAtPosition(Position $position)
    {
        $traverser = new NodeTraverser;
        $finder = new NodeAtPositionFinder($position);
        $traverser->addVisitor($finder);
        $traverser->traverse($this->stmts);
        return $finder->node;
    }

    /**
     * Returns the definition node for a fully qualified name
     *
     * @param string $fqn
     * @return Node|null
     */
    public function getDefinitionByFqn(string $fqn)
    {
        return $this->definitions[$fqn] ?? null;
    }

    /**
     * Returns a map from fully qualified name (FQN) to Nodes defined in this document
     *
     * @return Node[]
     */
    public function getDefinitions()
    {
        return $this->definitions;
    }

    /**
     * Returns a map from fully qualified name (FQN) to SymbolInformation
     *
     * @return SymbolInformation[]
     */
    public function getSymbols()
    {
        return $this->symbols;
    }

    /**
     * Returns true if the given FQN is defined in this document
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return bool
     */
    public function isDefined(string $fqn): bool
    {
        return isset($this->definitions[$fqn]);
    }

    /**
     * Returns the definition node for any node
     * The definition node MAY be in another document, check the ownerDocument attribute
     *
     * @param Node $node
     * @return Node|null
     */
    public function getDefinitionByNode(Node $node)
    {
        // Variables always stay in the boundary of the file and need to be searched inside their function scope
        // by traversing the AST
        if ($node instanceof Node\Expr\Variable) {
            return getVariableDefinition($node);
        }
        $fqn = getReferencedFqn($node);
        if (!isset($fqn)) {
            return null;
        }
        $document = $this->project->getDefinitionDocument($fqn);
        if (!isset($document)) {
            // If the node is a function or constant, it could be namespaced, but PHP falls back to global
            // http://php.net/manual/en/language.namespaces.fallback.php
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Expr\ConstFetch || $parent instanceof Node\Expr\FuncCall) {
                $parts = explode('\\', $fqn);
                $fqn = end($parts);
                $document = $this->project->getDefinitionDocument($fqn);
            }
        }
        if (!isset($document)) {
            return null;
        }
        return $document->getDefinitionByFqn($fqn);
    }

    /**
     * Returns the reference nodes for any node
     * The references node MAY be in other documents, check the ownerDocument attribute
     *
     * @param Node $node
     * @return Node[]
     */
    public function getReferencesByNode(Node $node)
    {
        // Variables always stay in the boundary of the file and need to be searched inside their function scope
        // by traversing the AST
        if ($node instanceof Node\Expr\Variable || $node instanceof Node\Param) {
            if ($node->name instanceof Node\Expr) {
                return null;
            }
            // Find function/method/closure scope
            $n = $node;
            while (isset($n) && !($n instanceof Node\FunctionLike)) {
                $n = $n->getAttribute('parentNode');
            }
            if (!isset($n)) {
                $n = $node->getAttribute('ownerDocument');
            }
            $traverser = new NodeTraverser;
            $refCollector = new VariableReferencesCollector($node->name);
            $traverser->addVisitor($refCollector);
            $traverser->traverse($n->getStmts());
            return $refCollector->references;
        }
        // Definition with a global FQN
        $fqn = getDefinedFqn($node);
        if ($fqn === null) {
            return [];
        }
        $refDocuments = $this->project->getReferenceDocuments($fqn);
        $nodes = [];
        foreach ($refDocuments as $document) {
            $refs = $document->getReferencesByFqn($fqn);
            if ($refs !== null) {
                foreach ($refs as $ref) {
                    $nodes[] = $ref;
                }
            }
        }
        return $nodes;
    }
}
