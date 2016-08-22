<?php

namespace LanguageServer\Protocol;

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
     * An data entry field that is preserved on a completion item between
     * a completion and a completion resolve request.
     *
     * @var mixed
     */
    public $data;
}
