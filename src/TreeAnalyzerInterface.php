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

interface TreeAnalyzerInterface {
    public function __construct(Parser $parser, $content, $docBlockFactory, $definitionResolver, $uri);

    public function getDiagnostics();

    public function getDefinitions();

    public function getDefinitionNodes();

    public function getReferenceNodes();

    public function getStmts();
}
