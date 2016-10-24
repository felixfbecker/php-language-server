<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;
use RuntimeException;

class ProtocolStreamWriter implements ProtocolWriter
{
    private $output;

    /**
     * @param resource $output
     */
    public function __construct($output)
    {
        $this->output = $output;
    }

    /**
     * Sends a Message to the client
     *
     * @param Message $msg
     * @return void
     */
    public function write(Message $msg)
    {
        $data = (string)$msg;
        $msgSize = strlen($data);
        $totalBytesWritten = 0;

        while ($totalBytesWritten < $msgSize) {
            error_clear_last();
            $bytesWritten = @fwrite($this->output, substr($data, $totalBytesWritten));
            if ($bytesWritten === false) {
                $error = error_get_last();
                if ($error !== null) {
                    throw new RuntimeException('Could not write message: ' . error_get_last()['message']);
                } else {
                    throw new RuntimeException('Could not write message');
                }
            }
            $totalBytesWritten += $bytesWritten;
        }
    }
}
