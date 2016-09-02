<?php

namespace LanguageServer\Protocol;

use AdvancedJsonRpc\Notification;

/**
 * Diagnostics notification are sent from the server to the client to signal results of validation runs.
 */
class PublishDiagnosticsNotification extends Notification
{
    /**
     * @var PublishDiagnosticsParams
     */
    public $params;

    /**
     * @param string $uri
     * @param Diagnostic[] $diagnostics
     */
    public function __construct(string $uri, array $diagnostics)
    {
        $this->method = 'textDocument/publishDiagnostics';
        $this->params = $params;
    }
}
