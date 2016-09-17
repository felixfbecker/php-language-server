<?php

namespace LanguageServer\Server;

use PhpParser\{Error, Comment, Node, ParserFactory, NodeTraverser, Lexer};
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\NodeVisitor\NameResolver;
use LanguageServer\{LanguageClient, ColumnCalculator, SymbolFinder, Project};
use LanguageServer\Protocol\{
    TextDocumentItem,
    TextDocumentIdentifier,
    VersionedTextDocumentIdentifier,
    Diagnostic,
    DiagnosticSeverity,
    Range,
    Position,
    FormattingOptions,
    TextEdit,
    SymbolInformation
};

/**
 * Provides method handlers for all workspace/* methods
 */
class Workspace
{
    /**
     * The lanugage client object to call methods on the client
     *
     * @var \LanguageServer\LanguageClient
     */
    private $client;

    private $project;

    public function __construct(Project $project, LanguageClient $client)
    {
        $this->project = $project;
        $this->client = $client;
    }

    /**
     * The workspace symbol request is sent from the client to the server to list project-wide symbols matching the query string.
     * document.
     *
     * @param string $query
     * @return SymbolInformation[]
     */
    public function symbol(string $query): array
    {
        return $this->project->findSymbols($query);
    }

    /**
     * A notification sent from the client to the server to signal the change of configuration settings.
     *
     * @param The actual changed settings
     * @return void
     */
    public function didChangeConfiguration($settings)
    {
    }

    /**
     * The document change notification is sent from the client to the server to signal changes to a text document.
     *
     * @param \LanguageServer\Protocol\FileEvent[] $textDocument
     * @return void
     */
    public function didChangeWatchedFiles(array $changes)
    {
    }
}
