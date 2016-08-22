<?php

namespace LanguageServer\Protocol\TextDocument;

/**
 * Represents a collection of [completion items](#CompletionItem) to be presented in
 * the editor.
 */
class CompletionList
{
    /**
     * This list it not complete. Further typing should result in recomputing this
     * list.
     *
     * @var bool
     */
    public $isIncomplete;

    /**
     * The completion items.
     *
     * @var CompletionItem[]
     */
    public $items;
}
