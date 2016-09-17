<?php

namespace LanguageServer;

use \LanguageServer\Protocol\{Diagnostic, DiagnosticSeverity, Range, Position, SymbolKind};

use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer, Parser};
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\NodeVisitor\NameResolver;

class PhpDocument
{
    private $stmts;
    private $client;
    private $project;
    private $symbols = [];
    private $parser;
    private $uri;

    public function __construct(string $uri, Project $project, LanguageClient $client, Parser $parser)
    {
        $this->uri = $uri;
        $this->project = $project;
        $this->client = $client;        
        $this->parser = $parser;
    }

    /**
     * Returns all symbols in this document.
     *
     * @return SymbolInformation[]
     */
    public function getSymbols()
    {
        return $this->symbols;
    }

    /**
     * Returns symbols in this document filtered by query string.
     *
     * @param string $query The search query
     * @return SymbolInformation[]
     */
    public function findSymbols(string $query)
    {
        return array_filter($this->symbols, function($symbol) use(&$query) {
            return stripos($symbol->name, $query) !== false;
        });
    }

    /**
     * Re-parses a source file, updates the AST and reports parsing errors that may occured as diagnostics
     *
     * @param string $content The new content of the source file
     * @return void
     */
    public function updateAst(string $content)
    {
        $stmts = null;
        try {
            $stmts = $this->parser->parse($content);
        }
        catch(Error $e) {
            // Parser still throws errors. e.g for unterminated comments
        }

        $diagnostics = [];
        foreach ($this->parser->getErrors() as $error) {
            $diagnostic = new Diagnostic();
            $diagnostic->range = new Range(
                new Position($error->getStartLine() - 1, $error->hasColumnInfo() ? $error->getStartColumn($content) - 1 : 0),
                new Position($error->getEndLine() - 1, $error->hasColumnInfo() ? $error->getEndColumn($content) : 0)
            );
            $diagnostic->severity = DiagnosticSeverity::ERROR;
            $diagnostic->source = 'php';
            // Do not include "on line ..." in the error message
            $diagnostic->message = $error->getRawMessage();
            $diagnostics[] = $diagnostic;
        }
        $this->client->textDocument->publishDiagnostics($this->uri, $diagnostics);

        // $stmts can be null in case of a fatal parsing error
        if ($stmts) {
            $traverser = new NodeTraverser;
            $finder = new SymbolFinder($this->uri);
            $traverser->addVisitor(new NameResolver);
            $traverser->addVisitor(new ColumnCalculator($content));
            $traverser->addVisitor($finder);
            $traverser->traverse($stmts);

            $this->stmts = $stmts;
            $this->symbols = $finder->symbols;
        }
    }

    /**
     * Returns this document as formatted text.
     *
     * @return string
     */
    public function getFormattedText()
    {
        if (empty($this->stmts)) {
            return [];
        }
        $prettyPrinter = new PrettyPrinter();
        $edit = new TextEdit();
        $edit->range = new Range(new Position(0, 0), new Position(PHP_INT_MAX, PHP_INT_MAX));
        $edit->newText = $prettyPrinter->prettyPrintFile($this->stmts);
        return [$edit];
    }
}
