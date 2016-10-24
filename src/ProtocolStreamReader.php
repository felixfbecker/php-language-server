<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;
use AdvancedJsonRpc\Message as MessageBody;
use Sabre\Event\Loop;
use RuntimeException;

class ProtocolStreamReader implements ProtocolReader
{
    const PARSE_HEADERS = 1;
    const PARSE_BODY = 2;

    private $input;
    private $parsingMode = self::PARSE_HEADERS;
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
        Loop\addReadStream($this->input, function () {
            if (feof($this->input)) {
                throw new RuntimeException('Stream is closed');
            }

            while (($c = fgetc($this->input)) !== false && $c !== '') {
                $this->buffer .= $c;
                switch ($this->parsingMode) {
                    case self::PARSE_HEADERS:
                        if ($this->buffer === "\r\n") {
                            $this->parsingMode = self::PARSE_BODY;
                            $this->contentLength = (int)$this->headers['Content-Length'];
                            $this->buffer = '';
                        } else if (substr($this->buffer, -2) === "\r\n") {
                            $parts = explode(':', $this->buffer);
                            $this->headers[$parts[0]] = trim($parts[1]);
                            $this->buffer = '';
                        }
                        break;
                    case self::PARSE_BODY:
                        if (strlen($this->buffer) === $this->contentLength) {
                            if (isset($this->listener)) {
                                $msg = new Message(MessageBody::parse($this->buffer), $this->headers);
                                $listener = $this->listener;
                                $listener($msg);
                            }
                            $this->parsingMode = self::PARSE_HEADERS;
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
