<?php

namespace LanguageServer\Protocol;

/**
 * A document highlight kind.
 */
abstract class DocumentHighlightKind
{
    /**
     * A textual occurrance.
     */
    const TEXT = 1;

    /**
     * Read-access of a symbol, like reading a variable.
     */
    const READ = 2;

    /**
     * Write-access of a symbol, like writing to a variable.
     */
    const WRITE = 3;
}
