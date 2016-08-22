<?php

namespace LanguageServer\Protocol;

/**
 * Format document on type options
 */
class DocumentOnTypeFormattingOptions
{
    /**
     * A character on which formatting should be triggered, like `}`.
     *
     * @var string
     */
    public $firstTriggerCharacter;

    /**
     * More trigger characters.
     *
     * @var string[]|null
     */
    public $moreTriggerCharacter;
}
