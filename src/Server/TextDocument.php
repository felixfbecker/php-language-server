<?php
declare(strict_types = 1);

namespace LanguageServer\Server;

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
    Location
};

/**
 * Provides method handlers for all textDocument/* methods
 */
class TextDocument
{
    /**
     * The lanugage client object to call methods on the client
     *
     * @var \LanguageServer\LanguageClient
     */
    private $client;

    /**
     * @var Project
     */
    private $project;

    public function __construct(Project $project, LanguageClient $client)
    {
        $this->project = $project;
        $this->client = $client;
    }

    /**
     * The document symbol request is sent from the client to the server to list all symbols found in a given text
     * document.
     *
     * @param \LanguageServer\Protocol\TextDocumentIdentifier $textDocument
     * @return SymbolInformation[]
     */
    public function documentSymbol(TextDocumentIdentifier $textDocument): array
    {
        return $this->project->getDocument($textDocument->uri)->getSymbols();
    }

    /**
     * The document open notification is sent from the client to the server to signal newly opened text documents. The
     * document's truth is now managed by the client and the server must not try to read the document's truth using the
     * document's uri.
     *
     * @param \LanguageServer\Protocol\TextDocumentItem $textDocument The document that was opened.
     * @return void
     */
    public function didOpen(TextDocumentItem $textDocument)
    {
        $this->project->getDocument($textDocument->uri)->updateContent($textDocument->text);
    }

    /**
     * The document change notification is sent from the client to the server to signal changes to a text document.
     *
     * @param \LanguageServer\Protocol\VersionedTextDocumentIdentifier $textDocument
     * @param \LanguageServer\Protocol\TextDocumentContentChangeEvent[] $contentChanges
     * @return void
     */
    public function didChange(VersionedTextDocumentIdentifier $textDocument, array $contentChanges)
    {
        $this->project->getDocument($textDocument->uri)->updateContent($contentChanges[0]->text);
    }


    /**
     * The document formatting request is sent from the server to the client to format a whole document.
     *
     * @param TextDocumentIdentifier $textDocument The document to format
     * @param FormattingOptions $options The format options
     * @return TextEdit[]
     */
    public function formatting(TextDocumentIdentifier $textDocument, FormattingOptions $options)
    {
        return $this->project->getDocument($textDocument->uri)->getFormattedText();
    }

    /**
     * The goto definition request is sent from the client to the server to resolve the definition location of a symbol
     * at a given text document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document
     * @param Position $position The position inside the text document
     * @return Location|Location[]|null
     */
    public function definition(TextDocumentIdentifier $textDocument, Position $position)
    {
        $document = $this->project->getDocument($textDocument->uri);
        $node = $document->getNodeAtPosition($position);
        if ($node === null) {
            return null;
        }
        $def = $document->getDefinitionByNode($node);
        if ($def === null) {
            return null;
        }
        return new Location(
            $def->getAttribute('ownerDocument')->getUri(),
            new Range(
                new Position($def->getAttribute('startLine') - 1, $def->getAttribute('startColumn') - 1),
                new Position($def->getAttribute('endLine') - 1, $def->getAttribute('endColumn') - 1)
            )
        );
    }
}
