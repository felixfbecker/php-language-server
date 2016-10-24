<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;
use Sabre\Event\Loop;
use RuntimeException;

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
            error_clear_last();
            $bytesWritten = @fwrite($this->output, $this->buffer);
            if ($bytesWritten === false) {
                $error = error_get_last();
                if ($error !== null) {
                    throw new RuntimeException('Could not write message: ' . error_get_last()['message']);
                } else {
                    throw new RuntimeException('Could not write message');
                }
            }
            else if ($bytesWritten > 0) {
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
