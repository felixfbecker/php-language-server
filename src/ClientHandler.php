<?php
declare(strict_types=1);

namespace LanguageServer;

use AdvancedJsonRpc;
use Amp\Deferred;
use Amp\Loop;
use LanguageServer\Event\MessageEvent;

class ClientHandler
{
    /**
     * @var ProtocolReader
     */
    public $protocolReader;

    /**
     * @var ProtocolWriter
     */
    public $protocolWriter;

    /**
     * @var IdGenerator
     */
    public $idGenerator;

    public function __construct(ProtocolReader $protocolReader, ProtocolWriter $protocolWriter)
    {
        $this->protocolReader = $protocolReader;
        $this->protocolWriter = $protocolWriter;
        $this->idGenerator = new IdGenerator;
    }

    /**
     * Sends a request to the client and returns a promise that is resolved with the result or rejected with the error
     *
     * @param string $method The method to call
     * @param array|object $params The method parameters
     * @return \Generator <mixed> Resolved with the result of the request or rejected with an error
     */
    public function request(string $method, $params): \Generator
    {
        $id = $this->idGenerator->generate();
        $deferred = new Deferred();
        $listener = function (MessageEvent $messageEvent) use ($id, $deferred, &$listener) {
            $msg = $messageEvent->getMessage();
            Loop::defer(function () use (&$listener, $deferred, $id, $msg) {
                if (AdvancedJsonRpc\Response::isResponse($msg->body) && $msg->body->id === $id) {
                    // Received a response
                    $this->protocolReader->removeListener('message', $listener);
                    if (AdvancedJsonRpc\SuccessResponse::isSuccessResponse($msg->body)) {
                        $deferred->resolve($msg->body->result);
                    } else {
                        $deferred->fail($msg->body->error);
                    }
                }
            });
        };
        $this->protocolReader->addListener('message', $listener);

        yield from $this->protocolWriter->write(
            new Message(
                new AdvancedJsonRpc\Request($id, $method, (object)$params)
            )
        );

        return yield $deferred->promise();
    }

    /**
     * Sends a notification to the client
     *
     * @param string $method The method to call
     * @param array|object $params The method parameters
     * @return \Generator <null> Will be resolved as soon as the notification has been sent
     */
    public function notify(string $method, $params): \Generator
    {
        return yield from $this->protocolWriter->write(
            new Message(
                new AdvancedJsonRpc\Notification($method, (object)$params)
            )
        );
    }
}
