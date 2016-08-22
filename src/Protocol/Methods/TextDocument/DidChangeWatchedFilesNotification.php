<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Notification;

/**
 * The watched files notification is sent from the client to the server when the client detects changes to files watched
 * by the language client.
 */
class DidChangeWatchedFilesNotification extends Notification
{
    /**
     * @var DidChangeWatchedFilesParams
     */
    public $params;
}
