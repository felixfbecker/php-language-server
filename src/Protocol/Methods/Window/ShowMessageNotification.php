<?php

namespace LanguageServer\Protocol\Methods\Windows;

use LanguageServer\Protocol\Notification;

/**
 * The show message notification is sent from a server to a client to ask the client to display a particular message in
 * the user interface.
 */
class ShowMessageNotification extends Notification
{
    /**
     * @var ShowMessageParams
     */
    public $params;
}
