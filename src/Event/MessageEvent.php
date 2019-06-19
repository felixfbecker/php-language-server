<?php
declare(strict_types=1);

namespace LanguageServer\Event;

use LanguageServer\Message;
use League\Event\Event;

class MessageEvent extends Event
{
    /**
     * @var Message
     */
    private $message;

    /**
     * Create a new event instance.
     *
     * @param string $name
     * @param Message $message
     */
    public function __construct(string $name, Message $message)
    {
        parent::__construct($name);
        $this->message = $message;
    }

    public function getMessage(): Message
    {
        return $this->message;
    }
}
