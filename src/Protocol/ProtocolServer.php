<?php

namespace LanguageServer\Protocol;

use Sabre\Event\Loop;
use Sabre\Event\EventEmitter;

abstract class ParsingMode {
    const HEADERS = 1;
    const BODY = 2;
}

class ProtocolServer extends EventEmitter
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
                        $req = Request::parse($body);
                        $this->emit($body->method, [$req]);
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
}
