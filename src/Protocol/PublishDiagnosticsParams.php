<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

class PublishDiagnosticsParams extends Params
{
    /**
     * The URI for which diagnostic information is reported.
     *
     * @var string
     */
    public $uri;

    /**
     * An array of diagnostic information items.
     *
     * @var LanguageServer\Protocol\Diagnostic[]
     */
    public $diagnostics;
}
