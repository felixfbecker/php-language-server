<?php
declare(strict_types = 1);

namespace LanguageServer\Protocol;

use LanguageServer\Definition;

class CompletionItem
{
    /**
     * The label of this completion item. By default
     * also the text that is inserted when selecting
     * this completion.
     *
     * @var string
     */
    public $label;

    /**
     * The kind of this completion item. Based of the kind
     * an icon is chosen by the editor.
     *
     * @var int|null
     */
    public $kind;

    /**
     * A human-readable string with additional information
     * about this item, like type or symbol information.
     *
     * @var string|null
     */
    public $detail;

    /**
     * A human-readable string that represents a doc-comment.
     *
     * @var string|null
     */
    public $documentation;

    /**
     * A string that shoud be used when comparing this item
     * with other items. When `falsy` the label is used.
     *
     * @var string|null
     */
    public $sortText;

    /**
     * A string that should be used when filtering a set of
     * completion items. When `falsy` the label is used.
     *
     * @var string|null
     */
    public $filterText;

    /**
     * A string that should be inserted a document when selecting
     * this completion. When `falsy` the label is used.
     *
     * @var string|null
     */
    public $insertText;

    /**
     * An edit which is applied to a document when selecting
     * this completion. When an edit is provided the value of
     * insertText is ignored.
     *
     * @var TextEdit|null
     */
    public $textEdit;

    /**
     * An optional array of additional text edits that are applied when
     * selecting this completion. Edits must not overlap with the main edit
     * nor with themselves.
     *
     * @var TextEdit[]|null
     */
    public $additionalTextEdits;

    /**
     * An optional command that is executed *after* inserting this completion. *Note* that
     * additional modifications to the current document should be described with the
     * additionalTextEdits-property.
     *
     * @var Command|null
     */
    public $command;

    /**
     * An data entry field that is preserved on a completion item between
     * a completion and a completion resolve request.
     *
     * @var mixed
     */
    public $data;

    /**
     * @param string          $label
     * @param int|null        $kind
     * @param string|null     $detail
     * @param string|null     $documentation
     * @param string|null     $sortText
     * @param string|null     $filterText
     * @param string|null     $insertText
     * @param TextEdit|null   $textEdit
     * @param TextEdit[]|null $additionalTextEdits
     * @param Command|null    $command
     * @param mixed|null      $data
     */
    public function __construct(
        string $label = null,
        int $kind = null,
        string $detail = null,
        string $documentation = null,
        string $sortText = null,
        string $filterText = null,
        string $insertText = null,
        TextEdit $textEdit = null,
        array $additionalTextEdits = null,
        Command $command = null,
        $data = null
    ) {
        $this->label = $label;
        $this->kind = $kind;
        $this->detail = $detail;
        $this->documentation = $documentation;
        $this->sortText = $sortText;
        $this->filterText = $filterText;
        $this->insertText = $insertText;
        $this->textEdit = $textEdit;
        $this->additionalTextEdits = $additionalTextEdits;
        $this->command = $command;
        $this->data = $data;
    }

    /**
     * Creates a CompletionItem for a Definition
     *
     * @param Definition $def
     * @return self
     */
    public static function fromDefinition(Definition $def): self
    {
        $item = new CompletionItem;
        $item->label = $def->symbolInformation->name;
        $item->kind = CompletionItemKind::fromSymbolKind($def->symbolInformation->kind);
        if ($def->type) {
            $item->detail = (string)$def->type;
        } else if ($def->symbolInformation->containerName) {
            $item->detail = $def->symbolInformation->containerName;
        }
        if ($def->documentation) {
            $item->documentation = $def->documentation;
        }
        if ($def->isStatic && $def->symbolInformation->kind === SymbolKind::PROPERTY) {
            $item->insertText = '$' . $def->symbolInformation->name;
        }
        return $item;
    }
}
