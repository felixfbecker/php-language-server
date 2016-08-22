<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Notification;

/**
 * The document open notification is sent from the client to the server to signal newly opened text documents. The
 * document's truth is now managed by the client and the server must not try to read the document's truth using the
 * document's uri.
 */
class DidOpenNotification extends Notification
{
    /**
     * @var DidOpenParams
     */
    public $params;
}
