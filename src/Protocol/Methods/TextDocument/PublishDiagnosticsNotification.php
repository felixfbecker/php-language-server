<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Notification;

/**
 * Diagnostics notification are sent from the server to the client to signal results of validation runs.
 */
class PublishDiagnosticsNotification extends Notification
{
    /**
     * @var PublishDiagnosticsParams
     */
    public $params;
}
