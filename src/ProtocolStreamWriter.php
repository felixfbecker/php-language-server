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
     * @return Promise Resolved when the message has been fully written out to the output stream
     */
    public function write(Message $msg)
    {
        // if the message queue is currently empty, register a write handler.
        if (empty($this->messages)) {
            Loop\addWriteStream($this->output, function () {
                $this->flush();
            });
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
     *
     * @return void
     */
    private function flush()
    {
        $keepWriting = true;
        while ($keepWriting) {
            $message = $this->messages[0]['message'];
            $promise = $this->messages[0]['promise'];

            $bytesWritten = @fwrite($this->output, $message);

            if ($bytesWritten > 0) {
                $message = substr($message, $bytesWritten);
            }

            // Determine if this message was completely sent
            if (strlen($message) === 0) {
                array_shift($this->messages);

                // This was the last message in the queue, remove the write handler.
                if (count($this->messages) === 0) {
                    Loop\removeWriteStream($this->output);
                    $keepWriting = false;
                }

                $promise->fulfill();
            } else {
                $this->messages[0]['message'] = $message;
                $keepWriting = false;
            }
        }
    }
}
