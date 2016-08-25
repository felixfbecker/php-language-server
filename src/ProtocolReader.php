<?php
declare(strict_types = 1);

namespace LanguageServer;

interface ProtocolReader
{
    public function onMessage(callable $listener);
}
