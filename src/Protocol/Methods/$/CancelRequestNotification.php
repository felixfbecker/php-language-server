<?php

namespace LanguageServer\Protocol\Methods;

use LanguageServer\Protocol\Notification;

class CancelRequestNotification extends Notification
{
    /**
     * @var CancelParams
     */
    public $params;
}
