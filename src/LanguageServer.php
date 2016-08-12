<?php

namespace LanguageServer;

use Sabre\Event\Loop;
use Sabre\Event\EventEmitter;

abstract class ParsingMode {
    const HEADERS = 1;
    const BODY = 2;
}

class LanguageServer extends EventEmitter
{
    private $input;
    private $output;

    /**
     * @param resource $input The input stream
     * @param resource $output The output stream
     */
    public function __construct($input, $output)
    {
        $this->input = $input;
        $this->output = $output;
    }

    public function listen()
    {
        $buffer = '';
        $parsingMode = ParsingMode::HEADERS;
        $headers = [];
        $contentLength = 0;

        Loop\addReadStream($this->input, function() use ($buffer, $parsingMode, $headers, $contentLength) {
            $buffer .= fgetc($this->output);
            switch ($parsingMode) {
                case ParsingMode::HEADERS:
                    if (substr($buffer, -4) === '\r\n\r\n') {
                        $parsingMode = ParsingMode::BODY;
                        $contentLength = (int)$headers['Content-Length'];
                        $buffer = '';
                    } else if (substr($buffer, -2) === '\r\n') {
                        $parts = explode(': ', $buffer);
                        $headers[$parts[0]] = $parts[1];
                        $buffer = '';
                    }
                    break;
                case ParsingMode::BODY:
                    if (strlen($buffer) === $contentLength) {
                        $body = json_decode($buffer);
                        $this->emit($body->method, [$body->params]);
                        $parsingMode = ParsingMode::HEADERS;
                        $buffer = '';
                    }
                    break;
            }
        });

        Loop\run();
    }
}
