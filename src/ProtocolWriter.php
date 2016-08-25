<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\Message;

interface ProtocolWriter
{
    public function write(Message $msg);
}
