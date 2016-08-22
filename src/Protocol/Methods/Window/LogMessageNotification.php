<?php

namespace LanguageServer\Protocol\Window;

use LanguageServer\Protocol\Notification;

/**
 * The log message notification is sent from the server to the client to ask the
 * client to log a particular message.
 */
class LogMessageNotification extends Notification
{
    /**
     * @var LogMessageParams
     */
    public $params;
}
