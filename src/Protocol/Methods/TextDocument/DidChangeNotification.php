<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Notification;

/**
 * The document change notification is sent from the client to the server to signal changes to a text document.
 */
class DidChangeNotification extends Notification
{
    /**
     * @var DidChangeParams
     */
    public $params;
}
