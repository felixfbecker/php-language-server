<?php

use LanguageServer\{LanguageServer, ProtocolStreamReader, ProtocolStreamWriter};
use Sabre\Event\Loop;
use Symfony\Component\Debug\ErrorHandler;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;

$options = getopt('', ['tcp::', 'memory-limit::']);

ini_set('memory_limit', $options['memory-limit'] ?? -1);

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

$logger = new Logger('Errors');
$logger->pushHandler(new StreamHandler(STDERR));
$errorHandler = new ErrorHandler;
$errorHandler->setDefaultLogger($logger);
ErrorHandler::register($errorHandler);

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
