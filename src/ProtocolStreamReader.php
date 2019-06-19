<?php
declare(strict_types=1);

namespace LanguageServer;

use AdvancedJsonRpc\Message as MessageBody;
use Amp\ByteStream\InputStream;
use Amp\Loop;
use LanguageServer\Event\MessageEvent;
use League\Event\Emitter;

class ProtocolStreamReader extends Emitter implements ProtocolReader
{
    private $input;

    public function __construct(InputStream $input)
    {
        $this->input = $input;
        Loop::defer(function () use (&$input) {
            $buffer = '';
            while (true) {
                $headers = [];
                while (true) {
                    while (($pos = strpos($buffer, "\r\n")) === false) {
                        $read = yield $input->read();
                        if ($read === null) {
                            return;
                        }
                        $buffer .= $read;
                    }

                    $headerLine = substr($buffer, 0, $pos);
                    $buffer = substr($buffer, (int)$pos + 2);
                    if (!$headerLine) {
                        break;
                    }
                    $headerPairs = \explode(': ', $headerLine);
                    $headers[$headerPairs[0]] = $headerPairs[1];
                }
                $contentLength = (int)$headers['Content-Length'];
                while (strlen($buffer) < $contentLength) {
                    $read = yield $this->input->read();
                    if ($read === null) {
                        return;
                    }
                    $buffer .= $read;
                }
                $body = substr($buffer, 0, $contentLength);
                $buffer = substr($buffer, $contentLength);
                $msg = new Message(MessageBody::parse($body), $headers);
                $this->emit(new MessageEvent('message', $msg));
            }
        });
    }
}
