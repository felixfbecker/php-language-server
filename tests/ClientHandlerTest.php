<?php
declare(strict_types=1);

namespace LanguageServer\Tests;

use AdvancedJsonRpc;
use Amp\Loop;
use LanguageServer\ClientHandler;
use LanguageServer\Event\MessageEvent;
use LanguageServer\Message;
use PHPUnit\Framework\TestCase;

class ClientHandlerTest extends TestCase
{
    public function testRequest()
    {
        Loop::run(function () {
            $reader = new MockProtocolStream;
            $writer = new MockProtocolStream;
            $handler = new ClientHandler($reader, $writer);
            $writer->addOneTimeListener('message', function (MessageEvent $messageEvent) use ($reader) {
                $msg = $messageEvent->getMessage();
                // Respond to request
                Loop::defer(function () use ($reader, $msg) {
                    yield from $reader->write(new Message(new AdvancedJsonRpc\SuccessResponse($msg->body->id, 'pong')));
                });
            });
            $result = yield from $handler->request('testMethod', ['ping']);
            $this->assertEquals('pong', $result);
            // No event listeners
            $this->assertEquals([], $reader->getListeners('message'));
            $this->assertEquals([], $writer->getListeners('message'));
        });
    }

    public function testNotify()
    {
        Loop::run(function () {
            $reader = new MockProtocolStream;
            $writer = new MockProtocolStream;
            $handler = new ClientHandler($reader, $writer);
            yield from $handler->notify('testMethod', ['ping']);
            // No event listeners
            $this->assertEquals([], $reader->getListeners('message'));
            $this->assertEquals([], $writer->getListeners('message'));
        });
    }
}
