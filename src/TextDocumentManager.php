<?php

namespace LanguageServer;

use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer};
use PhpParser\NodeVisitor\NameResolver;
use LanguageServer\Protocol\TextDocumentItem;

/**
 * Provides method handlers for all textDocument/* methods
 */
class TextDocumentManager
{
    /**
     * @var PhpParser\Parser
     */
    private $parser;

    /**
     * A map from file URIs to ASTs
     *
     * @var PhpParser\Stmt[][]
     */
    private $asts;

    public function __construct()
    {
        $lexer = new Lexer(['usedAttributes' => ['comments', 'startLine', 'endLine', 'startFilePos', 'endFilePos']]);
        $this->parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP7, $lexer, ['throwOnError' => false]);
    }

    /**
     * The document symbol request is sent from the client to the server to list all symbols found in a given text
     * document.
     *
     * @param LanguageServer\Protocol\TextDocumentIdentifier $textDocument
     * @return SymbolInformation[]
     */
    public function documentSymbol(TextDocumentIdentifier $textDocument): array
    {
        $stmts = $this->asts[$textDocument->uri];
        if (!$stmts) {
            return [];
        }
        $finder = new SymbolFinder($textDocument->uri);
        $traverser = new NodeTraverser;
        $traverser->addVisitor($finder);
        $traverser->traverse($stmts);
        return $finder->symbols;
    }

    /**
     * The document open notification is sent from the client to the server to signal newly opened text documents. The
     * document's truth is now managed by the client and the server must not try to read the document's truth using the
     * document's uri.
     *
     * @param LanguageServer\Protocol\TextDocumentItem $textDocument The document that was opened.
     * @return void
     */
    public function didOpen(TextDocumentItem $textDocument)
    {
        $this->updateAst($textDocument->uri, $textDocument->text);
    }

    /**
     * The document change notification is sent from the client to the server to signal changes to a text document.
     *
     * @param LanguageServer\Protocol\VersionedTextDocumentIdentifier $textDocument
     * @param LanguageServer\Protocol\TextDocumentContentChangeEvent[] $contentChanges
     * @return void
     */
    public function didChange(VersionedTextDocumentIdentifier $textDocument, array $contentChanges)
    {
        $this->updateAst($textDocument->uri, $contentChanges->text);
    }

    private function updateAst(string $uri, string $content)
    {
        $stmts = $parser->parse($content);
        // TODO report errors as diagnostics
        // foreach ($parser->getErrors() as $error) {
        //     error_log($error->getMessage());
        // }
        // $stmts can be null in case of a fatal parsing error
        if ($stmts) {
            $traverser = new NodeTraverser;
            $traverser->addVisitor(new NameResolver);
            $traverser->addVisitor(new ColumnCalculator($textDocument->text));
            $traverser->traverse($stmts);
        }
        $this->asts[$uri] = $stmts;
    }
}
