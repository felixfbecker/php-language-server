<?php
declare(strict_types=1);

namespace LanguageServer\Tests;

use AdvancedJsonRpc\{Request as RequestBody};
use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Deferred;
use Amp\Loop;
use LanguageServer\{Event\MessageEvent, ProtocolStreamReader};
use LanguageServer\Message;
use PHPUnit\Framework\TestCase;

class ProtocolStreamReaderTest extends TestCase
{
    public function getStreamPair()
    {
        $domain = \stripos(PHP_OS, "win") === 0 ? STREAM_PF_INET : STREAM_PF_UNIX;
        list($left, $right) = @\stream_socket_pair($domain, \STREAM_SOCK_STREAM, \STREAM_IPPROTO_IP);
        $a = new ResourceOutputStream($left);
        $b = new ResourceInputStream($right);
        return [$a, $b];
    }

    public function testParsingWorksAndListenerIsCalled()
    {
        Loop::run(function () {
            /** @var ResourceOutputStream $outputStream */
            /** @var ResourceInputStream $inputStream */
            list($outputStream, $inputStream) = $this->getStreamPair();

            $reader = new ProtocolStreamReader($inputStream);
            $deferred = new Deferred();
            $reader->addListener('message', function (MessageEvent $messageEvent) use (&$deferred) {
                $deferred->resolve($messageEvent->getMessage());
            });

            yield $outputStream->write((string)new Message(new RequestBody(1, 'aMethod', ['arg' => 'Hello World'])));
            $msg = yield $deferred->promise();
            $this->assertNotNull($msg);
            $this->assertInstanceOf(Message::class, $msg);
            $this->assertInstanceOf(RequestBody::class, $msg->body);
            $this->assertEquals(1, $msg->body->id);
            $this->assertEquals('aMethod', $msg->body->method);
            $this->assertEquals((object)['arg' => 'Hello World'], $msg->body->params);
        });
    }
}
