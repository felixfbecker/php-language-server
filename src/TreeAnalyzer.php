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
use PhpParser\{Error, ErrorHandler, Node, NodeTraverser, Parser};
use PhpParser\NodeVisitor\NameResolver;
use phpDocumentor\Reflection\DocBlockFactory;
use Sabre\Uri;
use Microsoft\PhpParser as Tolerant;

class TreeAnalyzer implements TreeAnalyzerInterface {
    private $parser;

    private $stmts;

    private $errorHandler;

    private $diagnostics;

    public function __construct(Parser $parser, $content, $docBlockFactory, $definitionResolver, $uri) {
        $this->uri = $uri;
        $this->parser = $parser;
        $this->docBlockFactory = $docBlockFactory;
        $this->definitionResolver = $definitionResolver;
        $this->content = $content;
        $errorHandler = new ErrorHandler\Collecting;
        $stmts = $this->parser->parse($content, $errorHandler);

        $this->diagnostics = [];
        foreach ($errorHandler->getErrors() as $error) {
            $this->diagnostics[] = Diagnostic::fromError($error, $this->content, DiagnosticSeverity::ERROR, 'php');
        }

        // $stmts can be null in case of a fatal parsing error <- Interesting. When do fatal parsing errors occur?
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
                $this->diagnostics[] = Diagnostic::fromError($error, $this->content, DiagnosticSeverity::WARNING, 'php');
            }

            $traverser = new NodeTraverser;

            // Collect all definitions
            $definitionCollector = new DefinitionCollector($this->definitionResolver);
            $traverser->addVisitor($definitionCollector);

            // Collect all references
            $referencesCollector = new ReferencesCollector($this->definitionResolver);
            $traverser->addVisitor($referencesCollector);

            $traverser->traverse($stmts);

            // Register this document on the project for all the symbols defined in it
            $this->definitions = $definitionCollector->definitions;
            $this->definitionNodes = $definitionCollector->nodes;
            foreach ($definitionCollector->definitions as $fqn => $definition) {
                // $this->index->setDefinition($fqn, $definition);
            }
            // Register this document on the project for references
            $this->referenceNodes = $referencesCollector->nodes;
            foreach ($referencesCollector->nodes as $fqn => $nodes) {
                // $this->index->addReferenceUri($fqn, $this->uri);
            }

            $this->stmts = $stmts;
        }
    }

    public function getDiagnostics() {
        return $this->diagnostics;
    }

    public function getDefinitions() {
        return $this->definitions ?? [];
    }

    public function getDefinitionNodes() {
        return $this->definitionNodes ?? [];
    }

    public function getReferenceNodes() {
        return $this->referenceNodes ?? [];
    }

    public function getStmts() {
        return $this->stmts;
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
}
