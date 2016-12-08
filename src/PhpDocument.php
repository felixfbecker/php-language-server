<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Range, Position, TextEdit};
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
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;
use Sabre\Uri;

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
     * The DefinitionResolver instance to resolve reference nodes to definitions
     *
     * @var DefinitionResolver
     */
    private $definitionResolver;

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
     * Map from fully qualified name (FQN) to Definition
     *
     * @var Definition[]
     */
    private $definitions;

    /**
     * Map from fully qualified name (FQN) to Node
     *
     * @var Node[]
     */
    private $definitionNodes;

    /**
     * Map from fully qualified name (FQN) to array of nodes that reference the symbol
     *
     * @var Node[][]
     */
    private $referenceNodes;

    /**
     * @param string          $uri             The URI of the document
     * @param string          $content         The content of the document
     * @param Project         $project         The Project this document belongs to (to load other documents)
     * @param Index           $index           The Index to register definitions etc
     * @param LanguageClient  $client          The LanguageClient instance (to report errors etc)
     * @param Parser          $parser          The PHPParser instance
     * @param DocBlockFactory $docBlockFactory The DocBlockFactory instance to parse docblocks
     */
    public function __construct(
        string $uri,
        string $content,
        Project $project,
        Index $index,
        LanguageClient $client,
        Parser $parser,
        DocBlockFactory $docBlockFactory,
        DefinitionResolver $definitionResolver
    ) {
        $this->uri = $uri;
        $this->project = $project;
        $this->client = $client;
        $this->parser = $parser;
        $this->docBlockFactory = $docBlockFactory;
        $this->definitionResolver = $definitionResolver;
        $this->updateContent($content);
    }

    /**
     * Get all references of a fully qualified name
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return Node[]
     */
    public function getReferenceNodesByFqn(string $fqn)
    {
        return isset($this->referenceNodes) && isset($this->referenceNodes[$fqn]) ? $this->referenceNodes[$fqn] : null;
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
            $definitionCollector = new DefinitionCollector($this->definitionResolver);
            $traverser->addVisitor($definitionCollector);

            // Collect all references
            $referencesCollector = new ReferencesCollector($this->definitionResolver);
            $traverser->addVisitor($referencesCollector);

            $traverser->traverse($stmts);

            // Unregister old definitions
            if (isset($this->definitions)) {
                foreach ($this->definitions as $fqn => $definition) {
                    $this->index->removeDefinition($fqn);
                }
            }
            // Register this document on the project for all the symbols defined in it
            $this->definitions = $definitionCollector->definitions;
            $this->definitionNodes = $definitionCollector->nodes;
            foreach ($definitionCollector->definitions as $fqn => $definition) {
                $this->index->setDefinition($fqn, $definition);
            }

            // Unregister old references
            if (isset($this->referenceNodes)) {
                foreach ($this->referenceNodes as $fqn => $node) {
                    $this->index->removeReferenceUri($fqn, $this->uri);
                }
            }
            // Register this document on the project for references
            $this->referenceNodes = $referencesCollector->nodes;
            foreach ($referencesCollector->nodes as $fqn => $nodes) {
                $this->index->addReferenceUri($fqn, $this->uri);
            }

            $this->stmts = $stmts;
        }

        if (!$this->isVendored()) {
            $this->client->textDocument->publishDiagnostics($this->uri, $diagnostics);
        }
    }

    /**
     * Returns true if the document is a dependency
     *
     * @return bool
     */
    public function isVendored(): bool
    {
        $path = Uri\parse($this->uri)['path'];
        return strpos($path, '/vendor/') !== false;
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
        if ($this->stmts === null) {
            return null;
        }
        $traverser = new NodeTraverser;
        $finder = new NodeAtPositionFinder($position);
        $traverser->addVisitor($finder);
        $traverser->traverse($this->stmts);
        return $finder->node;
    }

    /**
     * Returns a range of the content
     *
     * @param Range $range
     * @return string|null
     */
    public function getRange(Range $range)
    {
        if ($this->content === null) {
            return null;
        }
        $start = $range->start->toOffset($this->content);
        $length = $range->end->toOffset($this->content) - $start;
        return substr($this->content, $start, $length);
    }

    /**
     * Returns the definition node for a fully qualified name
     *
     * @param string $fqn
     * @return Node|null
     */
    public function getDefinitionNodeByFqn(string $fqn)
    {
        return $this->definitionNodes[$fqn] ?? null;
    }

    /**
     * Returns a map from fully qualified name (FQN) to Nodes defined in this document
     *
     * @return Node[]
     */
    public function getDefinitionNodes()
    {
        return $this->definitionNodes;
    }

    /**
     * Returns a map from fully qualified name (FQN) to Definition defined in this document
     *
     * @return Definition[]
     */
    public function getDefinitions()
    {
        return $this->definitions ?? [];
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
                $node instanceof Node\Expr\Variable
                || $node instanceof Node\Param
                || $node instanceof Node\Expr\ClosureUse
            ) {
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
                return $refCollector->nodes;
            }
            // Definition with a global FQN
            $fqn = DefinitionResolver::getDefinedFqn($node);
            if ($fqn === null) {
                return [];
            }
            $refDocuments = yield $this->project->getReferenceDocuments($fqn);
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
}
