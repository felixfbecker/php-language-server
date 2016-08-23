<?php

namespace LanguageServer\Protocol\Methods;

use LanguageServer\Protocol\Notification;

class CancelRequestNotification extends Notification
{
    /**
     * @var CancelRequestParams
     */
    public $params;
}
