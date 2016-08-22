<?php

namespace LanguageServer\Protocol;

/**
 * A notification message. A processed notification message must not send a response
 * back. They work like events.
 */
abstract class Notification extends Message
{
    /**
     * The method to be invoked.
     *
     * @var string
     */
    public $method;

    /**
     * The notification's params.
     *
     * @var mixed|null
     */
    public $params;
}
