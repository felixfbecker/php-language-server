<?php
declare(strict_types = 1);

namespace LanguageServer\Server;

use LanguageServer\{LanguageClient, Project};
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\Node;
use LanguageServer\Protocol\{
    TextDocumentItem,
    TextDocumentIdentifier,
    VersionedTextDocumentIdentifier,
    Position,
    Range,
    FormattingOptions,
    TextEdit,
    Location,
    SymbolInformation,
    ReferenceContext,
    Hover,
    MarkedString
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

    /**
     * @var PrettyPrinter
     */
    private $prettyPrinter;

    public function __construct(Project $project, LanguageClient $client)
    {
        $this->project = $project;
        $this->client = $client;
        $this->prettyPrinter = new PrettyPrinter();
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
        return array_values($this->project->getDocument($textDocument->uri)->getSymbols());
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
        $this->project->openDocument($textDocument->uri, $textDocument->text);
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
     * The document close notification is sent from the client to the server when the document got closed in the client.
     * The document's truth now exists where the document's uri points to (e.g. if the document's uri is a file uri the
     * truth now exists on disk).
     *
     * @param \LanguageServer\Protocol\TextDocumentItem $textDocument The document that was closed
     * @return void
     */
    public function didClose(TextDocumentIdentifier $textDocument)
    {
        $this->project->closeDocument($textDocument->uri);
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
     * The references request is sent from the client to the server to resolve project-wide references for the symbol
     * denoted by the given text document position.
     *
     * @param ReferenceContext $context
     * @return Location[]
     */
    public function references(ReferenceContext $context, TextDocumentIdentifier $textDocument, Position $position): array
    {
        $document = $this->project->getDocument($textDocument->uri);
        $node = $document->getNodeAtPosition($position);
        if ($node === null) {
            return [];
        }
        $refs = $document->getReferencesByNode($node);
        $locations = [];
        foreach ($refs as $ref) {
            $locations[] = Location::fromNode($ref);
        }
        return $locations;
    }

    /**
     * The goto definition request is sent from the client to the server to resolve the definition location of a symbol
     * at a given text document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document
     * @param Position $position The position inside the text document
     * @return Location|Location[]
     */
    public function definition(TextDocumentIdentifier $textDocument, Position $position)
    {
        $document = $this->project->getDocument($textDocument->uri);
        $node = $document->getNodeAtPosition($position);
        if ($node === null) {
            return [];
        }
        $def = $document->getDefinitionByNode($node);
        if ($def === null) {
            return [];
        }
        return Location::fromNode($def);
    }

    /**
     * The hover request is sent from the client to the server to request hover information at a given text document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document
     * @param Position $position The position inside the text document
     * @return Hover
     */
    public function hover(TextDocumentIdentifier $textDocument, Position $position): Hover
    {
        $document = $this->project->getDocument($textDocument->uri);
        // Find the node under the cursor
        $node = $document->getNodeAtPosition($position);
        if ($node === null) {
            return new Hover([]);
        }
        $range = Range::fromNode($node);
        // Get the definition node for whatever node is under the cursor
        $def = $document->getDefinitionByNode($node);
        if ($def === null) {
            return new Hover([], $range);
        }
        $contents = [];

        // Build a declaration string
        if ($def instanceof Node\Stmt\PropertyProperty || $def instanceof Node\Const_) {
            // Properties and constants can have multiple declarations
            // Use the parent node (that includes the modifiers), but only render the requested declaration
            $child = $def;
            $def = $def->getAttribute('parentNode');
            $defLine = clone $def;
            $defLine->props = [$child];
        } else {
            $defLine = clone $def;
        }
        // Don't include the docblock in the declaration string
        $defLine->setAttribute('comments', []);
        if (isset($defLine->stmts)) {
            $defLine->stmts = [];
        }
        $defText = $this->prettyPrinter->prettyPrint([$defLine]);
        $lines = explode("\n", $defText);
        if (isset($lines[0])) {
            $contents[] = new MarkedString('php', "<?php\n" . $lines[0]);
        }

        // Get the documentation string
        if ($def instanceof Node\Param) {
            $fn = $def->getAttribute('parentNode');
            $docBlock = $fn->getAttribute('docBlock');
            if ($docBlock !== null) {
                $tags = $docBlock->getTagsByName('param');
                foreach ($tags as $tag) {
                    if ($tag->getVariableName() === $def->name) {
                        $contents[] = $tag->getDescription()->render();
                        break;
                    }
                }
            }
        } else {
            $docBlock = $def->getAttribute('docBlock');
            if ($docBlock !== null) {
                $contents[] = $docBlock->getSummary();
            }
        }

        return new Hover($contents, $range);
    }

    /**
     * @param \LanguageServer\Protocol\TextDocumentIdentifier $textDocument
     * @param \LanguageServer\Protocol\Position $position
     *
     * @return \LanguageServer\Protocol\CompletionList
     */
    public function completion(TextDocumentIdentifier $textDocument, Position $position)
    {
        $document = $this->project->getDocument($textDocument->uri);
        return $document->complete($position);
    }

}
