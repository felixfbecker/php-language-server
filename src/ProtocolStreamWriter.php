<?php
declare(strict_types=1);

namespace LanguageServer;

use Amp\ByteStream\OutputStream;
use LanguageServer\Message;
use Sabre\Event\{
    Loop,
    Promise
};

class ProtocolStreamWriter implements ProtocolWriter
{
    /**
     * @var OutputStream $output
     */
    private $output;

    /**
     * @var array $messages
     */
    private $messages = [];

    /**
     * @param OutputStream $output
     */
    public function __construct(OutputStream $output)
    {
        $this->output = $output;
    }

    /**
     * {@inheritdoc}
     */
    public function write(Message $msg): \Generator
    {
        yield $this->output->write((string)$msg);
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
