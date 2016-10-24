<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;
use Sabre\Event\{
    Loop,
    Promise
};
use RuntimeException;

class ProtocolStreamWriter implements ProtocolWriter
{
    /**
     * @var resource $output
     */
    private $output;

    /**
     * @var array $messages
     */
    private $messages = [];

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
        // if the message queue is currently empty, register a write handler.
        if (empty($this->messages)) {
            Loop\addWriteStream($this->output, [$this, 'writeData']);
        }

        $promise = new Promise();
        $this->messages[] = [
            'message' => (string)$msg,
            'promise' => $promise
        ];
        return $promise;
    }

    /**
     * Writes pending messages to the output stream.
     * Must be public to be able to be used as a callback.
     *
     * @return void
     */
    public function writeData()
    {
        $message = $this->messages[0]['message'];
        $promise = $this->messages[0]['promise'];

        error_clear_last();
        $bytesWritten = @fwrite($this->output, $message);

        if ($bytesWritten === false) {
            $error = error_get_last();
            $promise->reject($error);
            if ($error !== null) {
                throw new RuntimeException('Could not write message: ' . error_get_last()['message']);
            } else {
                throw new RuntimeException('Could not write message');
            }
        } else if ($bytesWritten > 0) {
            $message = substr($message, $bytesWritten);
        }

        // Determine if this message was completely sent
        if (strlen($message) === 0) {
            array_shift($this->messages);

            // This was the last message in the queue, remove the write handler.
            if (count($this->messages) === 0) {
                Loop\removeWriteStream($this->output);
            }

            $promise->fulfill();
        } else {
            $this->messages[0]['message'] = $message;
        }
    }
}
