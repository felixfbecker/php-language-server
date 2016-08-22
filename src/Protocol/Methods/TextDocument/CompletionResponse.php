<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Response;

/**
 * Diagnostics notification are sent from the server to the client to signal results
 * of validation runs.
 */
class PublishDiagnosticsParams extends Response
{
    /**
     * @var CompletionItem[]|CompletionList
     */
    public $result;
}
