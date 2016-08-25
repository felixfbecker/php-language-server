<?php
declare(strict_types = 1);

namespace LanguageServer;

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
        fwrite($this->output, (string)$msg);
    }
}
