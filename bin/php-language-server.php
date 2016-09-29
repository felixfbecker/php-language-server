<?php

ini_set('memory_limit', '-1');

use LanguageServer\{LanguageServer, ProtocolStreamReader, ProtocolStreamWriter};
use Sabre\Event\Loop;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

if (count($argv) >= 3 && $argv[1] === '--tcp') {
    $address = $argv[2];
    $socket = stream_socket_client('tcp://' . $address, $errno, $errstr);
    if ($socket === false) {
        fwrite(STDERR, "Could not connect to language client. Error $errno\n");
        fwrite(STDERR, "$errstr\n");
        exit(1);
    }
    $inputStream = $outputStream = $socket;
} else {
    $inputStream = STDIN;
    $outputStream = STDOUT;
}

stream_set_blocking($inputStream, false);

$server = new LanguageServer(new ProtocolStreamReader($inputStream), new ProtocolStreamWriter($outputStream));

Loop\run();
