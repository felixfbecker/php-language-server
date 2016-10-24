<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;

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
            $bytesWritten = @fwrite($this->output, substr($data, $totalBytesWritten));
            $totalBytesWritten += $bytesWritten;
        }
    }
}
