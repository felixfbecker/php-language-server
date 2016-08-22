<?php

namespace LanguageServer\Protocol\Methods\Initialize\ServerCapabilities;

/**
 * Code Lens options.
 */
class CodeLensOptions
{
    /**
     * Code lens has a resolve provider as well.
     *
     * @var bool|null
     */
    public $resolveProvider;
}
