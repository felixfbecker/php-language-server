<?php
declare(strict_types = 1);

namespace LanguageServer\Tests;

use LanguageServer\{ProtocolReader, ProtocolWriter};
use LanguageServer\Protocol\Message;

/**
 * A fake duplex protocol stream
 */
class MockProtocolStream implements ProtocolReader, ProtocolWriter
{
    private $listener;

    /**
     * Sends a Message to the client
     *
     * @param Message $msg
     * @return void
     */
    public function write(Message $msg)
    {
        if (isset($this->listener)) {
            $listener = $this->listener;
            $listener(Message::parse((string)$msg));
        }
    }

    /**
     * @param callable $listener Is called with a Message object
     * @return void
     */
    public function onMessage(callable $listener)
    {
        $this->listener = $listener;
    }
}

