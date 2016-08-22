<?php

namespace LanguageServer\Protocol;

/**
 * Diagnostics notification are sent from the server to the client to signal results
 * of validation runs.
 */
class PublishDiagnosticsParams extends RequestParams
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
     * @var Diagnostic[]
     */
    public $diagnostics;
}
