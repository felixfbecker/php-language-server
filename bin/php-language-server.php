<?php

use LanguageServer\{LanguageServer, ProtocolStreamReader, ProtocolStreamWriter};
use Sabre\Event\Loop;
use Monolog\Logger;
use Monolog\Handler\StreamHandler;
use Monolog\Formatter\LineFormatter;
use Monolog\ErrorHandler;

$options = getopt('', ['tcp::', 'memory-limit::']);

ini_set('memory_limit', $options['memory-limit'] ?? -1);

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

function errorHandler($level, $message, $file, $line) {
    // error code is not included in error_reporting
    if (!(error_reporting() & $level)) {
        return;
    }

    if ($level !== E_DEPRECATED && $level !== E_USER_DEPRECATED) {
        throw new \ErrorException($message, 0, $level, $file, $line);
    }

    fwrite(STDERR, 'Deprecation Notice: '.$message.' in '.$file.':'.$line.'</warning>');
}

function setupLogging() {
    error_reporting(E_ALL | E_STRICT);
    set_error_handler('errorHandler');

    $formatter = new LineFormatter("[%datetime%] %level_name%: %message% %context% %extra%\n");
    $formatter->includeStacktraces(true);
    $formatter->ignoreEmptyContextAndExtra(true);

    $handler = new StreamHandler(STDERR);
    $handler->setFormatter($formatter);

    $logger = new Logger('php language server');
    $logger->pushHandler($handler);

    ErrorHandler::register($logger, false);
}

setupLogging();

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
