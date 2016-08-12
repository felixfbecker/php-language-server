<?php

use Sabre\Event\Loop;
use Sabre\Event\EventEmitter;

abstract class ParsingMode {
    const HEADERS = 1;
    const BODY = 2;
}

$eventEmitter = new EventEmitter();
$buffer = '';
$parsingMode = ParsingMode::HEADERS;
$headers = [];
$contentLength = 0;

Loop\addReadStream(STDIN, function() use ($buffer, $parsingMode, $headers, $contentLength, $eventEmitter) {
    $buffer .= fgetc(STDIN);
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
                $eventEmitter->emit($body->method, [$body->params]);
                $parsingMode = ParsingMode::HEADERS;
                $buffer = '';
            }
            break;
    }
});

Loop\run();
