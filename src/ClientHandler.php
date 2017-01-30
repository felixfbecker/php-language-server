<?php
declare(strict_types = 1);

namespace LanguageServer;

use AdvancedJsonRpc as JsonRpc;
use Rx\Observable;

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
     * @return Observable Emits JSON Patch operations for the result
     */
    public function request(string $method, $params): Observable
    {
        $id = $this->idGenerator->generate();
        return Observable::defer(function () {
            return $this->protocolWriter->write(
                new Protocol\Message(
                    new AdvancedJsonRpc\Request($id, $method, (object)$params)
                )
            );
        })
            // Wait for completion
            ->toArray()
            // Subscribe to message events
            ->flatMap(function () {
                return observableFromEvent($this->protocolReader, 'message');
            })
            ->flatMap(function (JsonRpc\Message $msg) {
                if (JsonRpc\Request::isRequest($msg->body) && $msg->body->method === '$/partialResult' && $msg->body->params->id === $id) {
                    return Observable::fromArray($msg->body->params->patch)->map(function ($operation) {
                        return Operation::fromDecodedJson($operation);
                    });
                }
                if (AdvancedJsonRpc\Response::isResponse($msg->body) && $msg->body->id === $id) {
                    if (AdvancedJsonRpc\SuccessResponse::isSuccessResponse($msg->body)) {
                        return Observable::just(new Operation\Replace('/', $msg->body->result));
                    }
                    return Observable::error($msg->body->error);
                }
                return Observable::emptyObservable();
            });
    }

    /**
     * Sends a notification to the client
     *
     * @param string $method The method to call
     * @param array|object $params The method parameters
     * @return Observable Will complete as soon as the notification has been sent
     */
    public function notify(string $method, $params): Observable
    {
        $id = $this->idGenerator->generate();
        return $this->protocolWriter->write(
            new Protocol\Message(
                new AdvancedJsonRpc\Notification($method, (object)$params)
            )
        );
    }
}
