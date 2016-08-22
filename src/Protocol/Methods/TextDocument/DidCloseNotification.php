<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Notification;

/**
 * The document close notification is sent from the client to the server when the document got closed in the client. The
 * document's truth now exists where the document's uri points to (e.g. if the document's uri is a file uri the truth
 * now exists on disk).
 */
class DidCloseNotification extends Notification
{
    /**
     * @var DidCloseParams
     */
    public $params;
}
