<?php
declare(strict_types = 1);

namespace LanguageServer\Server;

use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use PhpParser\{Node, NodeTraverser};
use LanguageServer\{LanguageClient, PhpDocumentLoader, PhpDocument, DefinitionResolver, CompletionProvider};
use LanguageServer\NodeVisitor\VariableReferencesCollector;
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
    MarkedString,
    SymbolKind,
    CompletionItem,
    CompletionItemKind
};
use LanguageServer\Index\ReadableIndex;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;

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

    /**
     * @var DefinitionResolver
     */
    private $definitionResolver;

    /**
     * @var CompletionProvider
     */
    private $completionProvider;

    /**
     * @var ReadableIndex
     */
    private $index;

    /**
     * @param PhpDocumentLoader  $documentLoader
     * @param DefinitionResolver $definitionResolver
     * @param LanguageClient     $client
     * @param ReadableIndex      $index
     */
    public function __construct(
        PhpDocumentLoader $documentLoader,
        DefinitionResolver $definitionResolver,
        LanguageClient $client,
        ReadableIndex $index
    ) {
        $this->documentLoader = $documentLoader;
        $this->client = $client;
        $this->prettyPrinter = new PrettyPrinter();
        $this->definitionResolver = $definitionResolver;
        $this->completionProvider = new CompletionProvider($this->definitionResolver, $index);
        $this->index = $index;
    }

    /**
     * The document symbol request is sent from the client to the server to list all symbols found in a given text
     * document.
     *
     * @param \LanguageServer\Protocol\TextDocumentIdentifier $textDocument
     * @return Promise <SymbolInformation[]>
     */
    public function documentSymbol(TextDocumentIdentifier $textDocument): Promise
    {
        return $this->documentLoader->getOrLoad($textDocument->uri)->then(function (PhpDocument $document) {
            $symbols = [];
            foreach ($document->getDefinitions() as $fqn => $definition) {
                $symbols[] = $definition->symbolInformation;
            }
            return $symbols;
        });
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
        $document = $this->documentLoader->open($textDocument->uri, $textDocument->text);
        if (!$document->isVendored()) {
            $this->client->textDocument->publishDiagnostics($textDocument->uri, $document->getDiagnostics());
        }
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
        $document = $this->documentLoader->get($textDocument->uri);
        $document->updateContent($contentChanges[0]->text);
        $this->client->textDocument->publishDiagnostics($textDocument->uri, $document->getDiagnostics());
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
        $this->documentLoader->close($textDocument->uri);
    }

    /**
     * The document formatting request is sent from the server to the client to format a whole document.
     *
     * @param TextDocumentIdentifier $textDocument The document to format
     * @param FormattingOptions $options The format options
     * @return Promise <TextEdit[]>
     */
    public function formatting(TextDocumentIdentifier $textDocument, FormattingOptions $options)
    {
        return $this->documentLoader->getOrLoad($textDocument->uri)->then(function (PhpDocument $document) {
            return $document->getFormattedText();
        });
    }

    /**
     * The references request is sent from the client to the server to resolve project-wide references for the symbol
     * denoted by the given text document position.
     *
     * @param ReferenceContext $context
     * @return Promise <Location[]>
     */
    public function references(
        ReferenceContext $context,
        TextDocumentIdentifier $textDocument,
        Position $position
    ): Promise {
        return coroutine(function () use ($textDocument, $position) {
            $document = yield $this->documentLoader->getOrLoad($textDocument->uri);
            $node = $document->getNodeAtPosition($position);
            if ($node === null) {
                return [];
            }
            $locations = [];
            // Variables always stay in the boundary of the file and need to be searched inside their function scope
            // by traversing the AST
            if (
                $node instanceof Node\Expr\Variable
                || $node instanceof Node\Param
                || $node instanceof Node\Expr\ClosureUse
            ) {
                if ($node->name instanceof Node\Expr) {
                    return null;
                }
                // Find function/method/closure scope
                $n = $node;
                while (isset($n) && !($n instanceof Node\FunctionLike)) {
                    $n = $n->getAttribute('parentNode');
                }
                if (!isset($n)) {
                    $n = $node->getAttribute('ownerDocument');
                }
                $traverser = new NodeTraverser;
                $refCollector = new VariableReferencesCollector($node->name);
                $traverser->addVisitor($refCollector);
                $traverser->traverse($n->getStmts());
                foreach ($refCollector->nodes as $ref) {
                    $locations[] = Location::fromNode($ref);
                }
            } else {
                // Definition with a global FQN
                $fqn = DefinitionResolver::getDefinedFqn($node);
                if ($fqn === null) {
                    return [];
                }
                $refDocuments = yield Promise\all(array_map(
                    [$this->documentLoader, 'getOrLoad'],
                    $this->index->getReferenceUris($fqn)
                ));
                foreach ($refDocuments as $document) {
                    $refs = $document->getReferenceNodesByFqn($fqn);
                    if ($refs !== null) {
                        foreach ($refs as $ref) {
                            $locations[] = Location::fromNode($ref);
                        }
                    }
                }
            }
            return $locations;
        });
    }

    /**
     * The goto definition request is sent from the client to the server to resolve the definition location of a symbol
     * at a given text document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document
     * @param Position $position The position inside the text document
     * @return Promise <Location|Location[]>
     */
    public function definition(TextDocumentIdentifier $textDocument, Position $position): Promise
    {
        return coroutine(function () use ($textDocument, $position) {
            $document = yield $this->documentLoader->getOrLoad($textDocument->uri);
            $node = $document->getNodeAtPosition($position);
            if ($node === null) {
                return [];
            }
            $def = $this->definitionResolver->resolveReferenceNodeToDefinition($node);
            if ($def === null || $def->symbolInformation === null) {
                return [];
            }
            return $def->symbolInformation->location;
        });
    }

    /**
     * The hover request is sent from the client to the server to request hover information at a given text document position.
     *
     * @param TextDocumentIdentifier $textDocument The text document
     * @param Position $position The position inside the text document
     * @return Promise <Hover>
     */
    public function hover(TextDocumentIdentifier $textDocument, Position $position): Promise
    {
        return coroutine(function () use ($textDocument, $position) {
            $document = yield $this->documentLoader->getOrLoad($textDocument->uri);
            // Find the node under the cursor
            $node = $document->getNodeAtPosition($position);
            if ($node === null) {
                return new Hover([]);
            }
            $range = Range::fromNode($node);
            // Get the definition for whatever node is under the cursor
            $def = $this->definitionResolver->resolveReferenceNodeToDefinition($node);
            if ($def === null) {
                return new Hover([], $range);
            }
            if ($def->declarationLine) {
                $contents[] = new MarkedString('php', "<?php\n" . $def->declarationLine);
            }
            if ($def->documentation) {
                $contents[] = $def->documentation;
            }
            return new Hover($contents, $range);
        });
    }

    /**
     * The Completion request is sent from the client to the server to compute completion items at a given cursor
     * position. Completion items are presented in the IntelliSense user interface. If computing full completion items
     * is expensive, servers can additionally provide a handler for the completion item resolve request
     * ('completionItem/resolve'). This request is sent when a completion item is selected in the user interface. A
     * typically use case is for example: the 'textDocument/completion' request doesn't fill in the documentation
     * property for returned completion items since it is expensive to compute. When the item is selected in the user
     * interface then a 'completionItem/resolve' request is sent with the selected completion item as a param. The
     * returned completion item should have the documentation property filled in.
     *
     * @param TextDocumentIdentifier The text document
     * @param Position $position The position
     * @return Promise <CompletionItem[]|CompletionList>
     */
    public function completion(TextDocumentIdentifier $textDocument, Position $position): Promise
    {
        return coroutine(function () use ($textDocument, $position) {
            $document = yield $this->documentLoader->getOrLoad($textDocument->uri);
            return $this->completionProvider->provideCompletion($document, $position);
        });
    }
}
