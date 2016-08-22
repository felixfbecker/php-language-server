<?php

namespace LanguageServer\Protocol\Methods\InitializeRequest;

use LanguageServer\Protocol\Notification;

/**
 * A notification sent from the client to the server to signal the change of
 * configuration settings.
 */
class DidChangeConfigurationNotification extends Notification
{
    /**
     * @var DidChangeConfigurationParams
     */
    public $params;
}
