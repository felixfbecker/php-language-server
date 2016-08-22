<?php

namespace LanguageServer\Protocol\Methods;

use LanguageServer\Protocol\Notification;

/**
 * A notification to ask the server to exit its process.
 */
class ExitNotification extends Notification
{
    /**
     * @var null
     */
    public $params;
}
