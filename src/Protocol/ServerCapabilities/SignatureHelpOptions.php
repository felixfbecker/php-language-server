<?php

namespace LanguageServer\Protocol\Methods\Initialize\ServerCapabilities;

/**
 * Signature help options.
 */
class SignatureHelpOptions
{
    /**
     * The characters that trigger signature help automatically.
     *
     * @var string[]|null
     */
    public $triggerCharacters;
}
