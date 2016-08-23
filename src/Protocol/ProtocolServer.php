<?php

namespace LanguageServer\Protocol;

use Sabre\Event\Loop;
use LanguageServer\Protocol\Methods\{InitializeRequest, InitializeResponse};

abstract class ParsingMode {
    const HEADERS = 1;
    const BODY = 2;
}

abstract class ProtocolServer
{
    private $input;
    private $output;
    private $buffer = '';
    private $parsingMode = ParsingMode::HEADERS;
    private $headers = [];
    private $contentLength = 0;

    /**
     * @param resource $input  The input stream
     * @param resource $output The output stream
     */
    public function __construct($input, $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    /**
     * Starts an event loop and listens on the provided input stream for messages, invoking method handlers and
     * responding on the provided output stream
     *
     * @return void
     */
    public function listen()
    {
        Loop\addReadStream($this->input, function() {
            $this->buffer .= fgetc($this->input);
            switch ($parsingMode) {
                case ParsingMode::HEADERS:
                    if (substr($buffer, -4) === '\r\n\r\n') {
                        $this->parsingMode = ParsingMode::BODY;
                        $this->contentLength = (int)$headers['Content-Length'];
                        $this->buffer = '';
                    } else if (substr($buffer, -2) === '\r\n') {
                        $parts = explode(': ', $buffer);
                        $headers[$parts[0]] = $parts[1];
                        $this->buffer = '';
                    }
                    break;
                case ParsingMode::BODY:
                    if (strlen($buffer) === $contentLength) {
                        $msg = Message::parse($body, Request::class);
                        $result = null;
                        $err = null;
                        try {
                            // Invoke the method handler to get a result
                            $result = $this->dispatch($msg);
                        } catch (ResponseError $e) {
                            // If a ResponseError is thrown, send it back in the Response (result will be null)
                            $err = $e;
                        } catch (Throwable $e) {
                            // If an unexpected error occured, send back an INTERNAL_ERROR error response (result will be null)
                            $err = new ResponseError(
                                $e->getMessage(),
                                $e->getCode() === 0 ? ErrorCode::INTERNAL_ERROR : $e->getCode(),
                                null,
                                $e
                            );
                        }
                        // Only send a Response for a Request
                        // Notifications do not send Responses
                        if ($msg instanceof Request) {
                            $this->send(new Response($msg->id, $msg->method, $result, $err));
                        }
                        $this->parsingMode = ParsingMode::HEADERS;
                        $this->buffer = '';
                    }
                    break;
            }
        });

        Loop\run();
    }

    /**
     * Calls the appropiate method handler for an incoming Message
     *
     * @param Message $msg The incoming message
     * @return Result|void
     */
    private function dispatch(Message $msg)
    {
        // Find out the object and function that should be called
        $obj = $this;
        $parts = explode('/', $msg->method);
        // The function to call is always the last part of the method
        $fn = array_pop($parts);
        // For namespaced methods like textDocument/didOpen, call the didOpen method on the $textDocument property
        // For simple methods like initialize, shutdown, exit, this loop will simply not be entered and $obj will be
        // the server ($this)
        foreach ($parts as $part) {
            if (!isset($obj->$part)) {
                throw new ResponseError("Method {$msg->method} is not implemented", ErrorCode::METHOD_NOT_FOUND);
            }
            $obj = $obj->$part;
        }
        // Check if $fn exists on $obj
        if (!method_exists($obj, $fn)) {
            throw new ResponseError("Method {$msg->method} is not implemented", ErrorCode::METHOD_NOT_FOUND);
        }
        // Invoke the method handler and return the result
        return $obj->$fn($msg->params);
    }

    /**
     * Sends a Message to the client (for example a Response)
     *
     * @param Message $msg
     * @return void
     */
    private function send(Message $msg)
    {
        fwrite($this->output, json_encode($msg));
    }

    /**
     * The initialize request is sent as the first request from the client to the server.
     * The default implementation returns no capabilities.
     *
     * @param LanguageServer\Protocol\Methods\InitializeParams $params
     * @return LanguageServer\Protocol\Methods\IntializeResult
     */
    protected function initialize(InitializeParams $params): InitializeResult
    {
        return new InitializeResult();
    }

    /**
     * The shutdown request is sent from the client to the server. It asks the server to shut down, but to not exit
     * (otherwise the response might not be delivered correctly to the client). There is a separate exit notification that
     * asks the server to exit.
     * The default implementation does nothing.
     *
     * @return void
     */
    protected function shutdown()
    {

    }

    /**
     * A notification to ask the server to exit its process.
     * The default implementation does exactly this.
     *
     * @return void
     */
    protected function exit()
    {
        exit(0);
    }
}
