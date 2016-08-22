<?php

namespace LanguageServer\Protocol;

use Sabre\Event\Loop;
use LanguageServer\Protocol\Methods\Initialize\{InitializeRequest, InitializeResponse};

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

    public function listen()
    {

        Loop\addReadStream($this->input, function() {
            $this->buffer .= fgetc($this->output);
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
                        $req = Message::parse($body, Request::class);
                        if (!method_exists($this, $req->method)) {
                            $this->sendResponse(new Response(null, new ResponseError("Method {$req->method} is not implemented", ErrorCode::METHOD_NOT_FOUND, $e)));
                        } else {
                            try {
                                $result = $this->{$req->method}($req->params);
                                $this->sendResponse(new Response($result));
                            } catch (ResponseError $e) {
                                $this->sendResponse(new Response(null, $e));
                            } catch (Throwable $e) {
                                $this->sendResponse(new Response(null, new ResponseError($e->getMessage(), $e->getCode(), null, $e)));
                            }
                        }
                        $this->parsingMode = ParsingMode::HEADERS;
                        $this->buffer = '';
                    }
                    break;
            }
        });

        Loop\run();
    }

    public function sendResponse(Response $res)
    {
        fwrite($this->output, json_encode($res));
    }

    abstract public function initialize(InitializeRequest $req): InitializeResponse;
}
