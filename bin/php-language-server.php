<?php

use Amp\ByteStream\ResourceInputStream;
use Amp\ByteStream\ResourceOutputStream;
use Amp\Loop;
use Amp\Socket\ClientSocket;
use Amp\Socket\ServerSocket;
use Composer\XdebugHandler\XdebugHandler;
use LanguageServer\{LanguageServer, ProtocolStreamReader, ProtocolStreamWriter, StderrLogger};

$options = getopt('', ['tcp::', 'tcp-server::', 'memory-limit::']);

ini_set('memory_limit', $options['memory-limit'] ?? '4G');

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

$logger = new StderrLogger();

// Only write uncaught exceptions to STDERR, not STDOUT
set_exception_handler(function (\Throwable $e) use ($logger) {
    $logger->critical((string)$e);
});

@cli_set_process_title('PHP Language Server');

// If XDebug is enabled, restart without it
$xdebugHandler = new XdebugHandler('PHPLS');
$xdebugHandler->setLogger($logger);
$xdebugHandler->check();
unset($xdebugHandler);

if (!empty($options['tcp'])) {
    // Connect to a TCP server
    $address = $options['tcp'];
    $server = function () use ($logger, $address) {
        /** @var ClientSocket $socket */
        $socket = yield Amp\Socket\connect('tcp://' . $address);
        $ls = new LanguageServer(
            new ProtocolStreamReader($socket),
            new ProtocolStreamWriter($socket)
        );
        yield $ls->getshutdownDeferred();
    };
} else if (!empty($options['tcp-server'])) {
    // Run a TCP Server
    $address = $options['tcp-server'];
    $server = function () use ($logger, $address) {

        $server = Amp\Socket\listen('tcp://' . $address);

        $logger->debug("Server listening on $address");

        while ($socket = yield $server->accept()) {
            /** @var ServerSocket $socket */
            list($ip, $port) = \explode(':', $socket->getRemoteAddress());

            $logger->debug("Accepted connection from {$ip}:{$port}." . PHP_EOL);

            Loop::run(function () use ($socket) {
                $ls = new LanguageServer(
                    new ProtocolStreamReader($socket),
                    new ProtocolStreamWriter($socket)
                );
                yield $ls->getshutdownDeferred();
            });
        }
    };
} else {
    // Use STDIO
    $logger->debug('Listening on STDIN');
    $inputStream = new ResourceInputStream(STDIN);
    $outputStream = new ResourceOutputStream(STDOUT);
    $ls = new LanguageServer(
        new ProtocolStreamReader($inputStream),
        new ProtocolStreamWriter($outputStream)
    );
    $server = function () use ($ls) {
        yield $ls->getshutdownDeferred();
    };
}

Loop::run($server);
