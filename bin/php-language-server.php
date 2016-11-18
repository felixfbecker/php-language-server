<?php

use LanguageServer\{LanguageServer, ProtocolStreamReader, ProtocolStreamWriter};
use Sabre\Event\Loop;

$options = getopt('', ['tcp::', 'memory-limit::']);

ini_set('memory_limit', $options['memory-limit'] ?? -1);

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

// Convert all errors to ErrorExceptions
set_error_handler(function (int $severity, string $message, string $file, int $line) {
    if (!(error_reporting() & $severity)) {
        // This error code is not included in error_reporting (can also be caused by the @ operator)
        return;
    }
    throw new \ErrorException($message, 0, $severity, $file, $line);
});

// Only write uncaught exceptions to STDERR, not STDOUT
set_exception_handler(function (\Throwable $e) {
    fwrite(STDERR, (string)$e);
});

@cli_set_process_title('PHP Language Server');

if (!empty($options['tcp'])) {
    $address = $options['tcp'];
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
