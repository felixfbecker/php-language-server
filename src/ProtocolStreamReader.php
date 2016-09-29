<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;
use AdvancedJsonRpc\Message as MessageBody;
use Sabre\Event\EventEmitter;
use Sabre\Event\Loop;

abstract class ParsingMode
{
    const HEADERS = 1;
    const BODY = 2;
}

class ProtocolStreamReader implements ProtocolReader
{
    private $input;
    private $parsingMode = ParsingMode::HEADERS;
    private $buffer = '';
    private $headers = [];
    private $contentLength;
    private $listener;

    /**
     * @param resource $input
     */
    public function __construct($input)
    {
        $this->input = $input;
        Loop\addReadStream($this->input, function() {
            while (($c = fgetc($this->input)) !== false && $c !== '') {
                $this->buffer .= $c;
                switch ($this->parsingMode) {
                    case ParsingMode::HEADERS:
                        if ($this->buffer === "\r\n") {
                            $this->parsingMode = ParsingMode::BODY;
                            $this->contentLength = (int)$this->headers['Content-Length'];
                            $this->buffer = '';
                        } else if (substr($this->buffer, -2) === "\r\n") {
                            $parts = explode(':', $this->buffer);
                            $this->headers[$parts[0]] = trim($parts[1]);
                            $this->buffer = '';
                        }
                        break;
                    case ParsingMode::BODY:
                        if (strlen($this->buffer) === $this->contentLength) {
                            if (isset($this->listener)) {
                                $msg = new Message(MessageBody::parse($this->buffer), $this->headers);
                                $listener = $this->listener;
                                $listener($msg);
                            }
                            $this->parsingMode = ParsingMode::HEADERS;
                            $this->headers = [];
                            $this->buffer = '';
                        }
                        break;
                }
            }
        });
    }

    /**
     * @param callable $listener Is called with a Message object
     * @return void
     */
    public function onMessage(callable $listener)
    {
        $this->listener = $listener;
    }
}
