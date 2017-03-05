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

class TolerantTreeAnalyzer implements TreeAnalyzerInterface {
    private $parser;

    /** @var Tolerant\Node */
    private $stmts;

    /**
     * TolerantTreeAnalyzer constructor.
     * @param Tolerant\Parser $parser
     * @param $content
     * @param $docBlockFactory
     * @param TolerantDefinitionResolver $definitionResolver
     * @param $uri
     */
    public function __construct($parser, $content, $docBlockFactory, $definitionResolver, $uri) {
        $this->uri = $uri;
        $this->parser = $parser;
        $this->docBlockFactory = $docBlockFactory;
        $this->definitionResolver = $definitionResolver;
        $this->content = $content;
        $this->stmts = $this->parser->parseSourceFile($content, $uri);

        // TODO - docblock errors

         foreach ($this->stmts->getDescendantNodes() as $node) {
            $fqn = $definitionResolver::getDefinedFqn($node);
            // Only index definitions with an FQN (no variables)
            if ($fqn === null) {
                continue;
            }
            $this->definitionNodes[$fqn] = $node;
            $this->definitions[$fqn] = $this->definitionResolver->createDefinitionFromNode($node, $fqn);
        }
    }

    public function getDiagnostics() {
        $diagnostics = [];
        $content = $this->stmts->getFileContents();
        foreach (Tolerant\DiagnosticsProvider::getDiagnostics($this->stmts) as $_error) {
            $range = Tolerant\PositionUtilities::getRangeFromPosition($_error->start, $_error->length, $content);

            $diagnostics[] = new Diagnostic(
                $_error->message,
                new Range(
                    new Position($range->start->line, $range->start->character),
                    new Position($range->end->line, $range->start->character)
                )
            );
        }
        return $diagnostics;
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
