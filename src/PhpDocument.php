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
    ReferencesCollector
};
use LanguageServer\Index\Index;
use PhpParser\{Error, ErrorHandler, Node, NodeTraverser};
use PhpParser\NodeVisitor\NameResolver;
use phpDocumentor\Reflection\DocBlockFactory;
use Sabre\Uri;
use Microsoft\PhpParser as Tolerant;

class PhpDocument
{
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
     * @var DefinitionResolverInterface
     */
    private $definitionResolver;

    /**
     * @var Index
     */
    private $index;

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
     * @var Node[] | Tolerant\Node
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
     * Diagnostics for this document that were collected while parsing
     *
     * @var Diagnostic[]
     */
    private $diagnostics;

    /**
     * @param string $uri The URI of the document
     * @param string $content The content of the document
     * @param Index $index The Index to register definitions and references to
     * @param Parser $parser The PHPParser instance
     * @param DocBlockFactory $docBlockFactory The DocBlockFactory instance to parse docblocks
     * @param DefinitionResolverInterface $definitionResolver The DefinitionResolver to resolve definitions to symbols in the workspace
     */
    public function __construct(
        string $uri,
        string $content,
        Index $index,
        $parser,
        DocBlockFactory $docBlockFactory,
        DefinitionResolverInterface $definitionResolver
    ) {
        $this->uri = $uri;
        $this->index = $index;
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
     * that may have occurred as diagnostics.
     *
     * @param string $content
     * @return void
     */
    public function updateContent(string $content)
    {
        $this->content = $content;

        // Unregister old definitions
        if (isset($this->definitions)) {
            foreach ($this->definitions as $fqn => $definition) {
                $this->index->removeDefinition($fqn);
            }
        }

        // Unregister old references
        if (isset($this->referenceNodes)) {
            foreach ($this->referenceNodes as $fqn => $node) {
                $this->index->removeReferenceUri($fqn, $this->uri);
            }
        }

        $this->referenceNodes = null;
        $this->definitions = null;
        $this->definitionNodes = null;

        $treeAnalyzer = ParserResourceFactory::getTreeAnalyzer($this->parser, $content, $this->docBlockFactory, $this->definitionResolver, $this->uri);

        $this->diagnostics = $treeAnalyzer->getDiagnostics();

        $this->definitions = $treeAnalyzer->getDefinitions();

        $this->definitionNodes = $treeAnalyzer->getDefinitionNodes();

        $this->referenceNodes = $treeAnalyzer->getReferenceNodes();
        
        foreach ($this->definitions as $fqn => $definition) {
            $this->index->setDefinition($fqn, $definition);
        }

        // Register this document on the project for references
        foreach ($this->referenceNodes as $fqn => $nodes) {
            $this->index->addReferenceUri($fqn, $this->uri);
        }
        
        $this->stmts = $treeAnalyzer->getStmts();
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
     * Returns this document's diagnostics
     *
     * @return Diagnostic[]
     */
    public function getDiagnostics()
    {
        return $this->diagnostics;
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

        if (\is_array($this->stmts)) {
            $traverser = new NodeTraverser;
            $finder = new NodeAtPositionFinder($position);
            $traverser->addVisitor($finder);
            $traverser->traverse($this->stmts);
            return $finder->node;
        } else {
            $offset = $position->toOffset($this->stmts->getFileContents());
            return $this->stmts->getDescendantNodeAtPosition($offset);
        }
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
}
