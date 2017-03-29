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

    private $diagnostics;    

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

        foreach ($this->stmts->getDescendantNodesAndTokens() as $node) {
             if ($node instanceof Tolerant\Node) {
                $fqn = $definitionResolver::getDefinedFqn($node);
                // Only index definitions with an FQN (no variables)
                if ($fqn !== null) {
                    $this->definitionNodes[$fqn] = $node;
                    $this->definitions[$fqn] = $this->definitionResolver->createDefinitionFromNode($node, $fqn);
                }

                 $parent = $node->parent;
                 if (
                     ($node instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression
                         && !(
                             $node->parent instanceof Tolerant\Node\Expression\CallExpression ||
                             $node->memberName instanceof Tolerant\Token
                         ))
                     || ($parent instanceof Tolerant\Node\Statement\NamespaceDefinition && $parent->name !== null && $parent->name->getStart() === $node->getStart())
                 ) {
                     continue;
                 }
                 $fqn = $definitionResolver->resolveReferenceNodeToFqn($node);
                 if ($fqn !== null) {
                     $this->addReference($fqn, $node);

                     if (
                         $node instanceof Tolerant\Node\QualifiedName
                         && $node->isQualifiedName()
                         && !($parent instanceof Tolerant\Node\Statement\NamespaceDefinition && $parent->name->getStart() === $node->getStart()
                         )
                     ) {
                         // Add references for each referenced namespace
                         $ns = $fqn;
                         while (($pos = strrpos($ns, '\\')) !== false) {
                             $ns = substr($ns, 0, $pos);
                             $this->addReference($ns, $node);
                         }
                     }

                     // Namespaced constant access and function calls also need to register a reference
                     // to the global version because PHP falls back to global at runtime
                     // http://php.net/manual/en/language.namespaces.fallback.php
                     if (TolerantDefinitionResolver::isConstantFetch($node) ||
                         ($parent instanceof Tolerant\Node\Expression\CallExpression
                             && !(
                                 $node instanceof Tolerant\Node\Expression\ScopedPropertyAccessExpression
                             ))) {
                         $parts = explode('\\', $fqn);
                         if (count($parts) > 1) {
                             $globalFqn = end($parts);
                             $this->addReference($globalFqn, $node);
                         }
                     }
                 }
             }

             if (($_error = Tolerant\DiagnosticsProvider::checkDiagnostics($node)) !== null) {
                 $range = Tolerant\PositionUtilities::getRangeFromPosition($_error->start, $_error->length, $content);

                 $this->diagnostics[] = new Diagnostic(
                     $_error->message,
                     new Range(
                         new Position($range->start->line, $range->start->character),
                         new Position($range->end->line, $range->start->character)
                     )
                 );
             }
         }
    }
    
    public function getDiagnostics() {
        return $this->diagnostics ?? [];
    }

    private function addReference(string $fqn, Tolerant\Node $node)
    {
        if (!isset($this->referenceNodes[$fqn])) {
            $this->referenceNodes[$fqn] = [];
        }
        $this->referenceNodes[$fqn][] = $node;
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
