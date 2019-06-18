<?php
declare(strict_types=1);

namespace LanguageServer\Tests;

use Amp\Deferred;
use Amp\Delayed;
use Amp\Loop;
use LanguageServer\{Event\MessageEvent, ProtocolReader, ProtocolWriter};
use LanguageServer\Message;
use League\Event\Emitter;
use League\Event\Event;

/**
 * A fake duplex protocol stream
 */
class MockProtocolStream extends Emitter implements ProtocolReader, ProtocolWriter
{
    /**
     * Sends a Message to the client
     *
     * @param Message $msg
     * @return void
     */
    public function write(Message $msg): \Generator
    {
        Loop::defer(function () use ($msg) {
            $this->emit(new MessageEvent('message', Message::parse((string)$msg)));
        });
        yield new Delayed(0);
    }
}
