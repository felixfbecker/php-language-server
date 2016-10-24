<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;
use Sabre\Event\Loop;

class ProtocolStreamWriter implements ProtocolWriter
{
    private $output;

    /**
     * @var string $buffer
     */
    private $buffer;

    /**
     * @param resource $output
     */
    public function __construct($output)
    {
        $this->output = $output;
        Loop\addWriteStream($this->output, function () {
            $msgSize = strlen($this->buffer);
            $bytesWritten = @fwrite($this->output, $this->buffer);
            if ($bytesWritten > 0) {
                $this->buffer = substr($this->buffer, $bytesWritten);
            }
        });
    }

    /**
     * Sends a Message to the client
     *
     * @param Message $msg
     * @return void
     */
    public function write(Message $msg)
    {
        $this->buffer .= $msg;
    }
}
