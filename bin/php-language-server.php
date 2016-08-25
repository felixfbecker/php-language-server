<?php

use LanguageServer\{LanguageServer, ProtocolStreamReader, ProtocolStreamWriter};
use Sabre\Event\Loop;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

$server = new LanguageServer(new ProtocolStreamReader(STDIN), new ProtocolStreamWriter(STDOUT));

Loop\run();
