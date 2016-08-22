<?php

namespace LanguageServer\Protocol;

/**
 * Contains additional diagnostic information about the context in which
 * a code action is run.
 */
class CodeActionContext
{
    /**
     * An array of diagnostics.
     *
     * @var Diagnostic[]
     */
    public $diagnostics;
}
