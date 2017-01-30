<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;
use Rx\Observable;

interface ProtocolWriter
{
    /**
     * Sends a Message to the client
     *
     * @param Message $msg
     * @return Observable Resolved when the message has been fully written out to the output stream
     */
    public function write(Message $msg): Observable;
}
