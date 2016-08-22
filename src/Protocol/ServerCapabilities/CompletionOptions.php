<?php

namespace LanguageServer\Protocol\Methods\Initialize\ServerCapabilities;

/**
 * Completion options.
 */
class CompletionOptions
{
    /*
     * The server provides support to resolve additional information for a completion
     * item.
     *
     * @var bool
     */
    public $resolveProvider;

    /**
     * The characters that trigger completion automatically.
     *
     * @var string|null
     */
    public $triggerCharacters;
}
