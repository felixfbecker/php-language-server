<?php

namespace LanguageServer\Tests;
require __DIR__ . '/vendor/autoload.php';

use Exception;
use LanguageServer\Index\Index;
use LanguageServer\PhpDocument;
use LanguageServer\DefinitionResolver;
use Microsoft\PhpParser;
use phpDocumentor\Reflection\DocBlockFactory;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Composer\{Factory, XdebugHandler};

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

// If XDebug is enabled, restart without it
(new XdebugHandler(Factory::createOutput()))->check();

$totalSize = 0;

$frameworks = ["drupal", "wordpress", "php-language-server", "tolerant-php-parser", "math-php", "symfony", "codeigniter", "cakephp"];

foreach($frameworks as $framework) {
    $iterator = new RecursiveDirectoryIterator(__DIR__ . "/validation/frameworks/$framework");
    $testProviderArray = array();

    foreach (new RecursiveIteratorIterator($iterator) as $file) {
        if (strpos((string)$file, ".php") !== false) {
            $totalSize += $file->getSize();
            $testProviderArray[] = $file->getPathname();
        }
    }

    if (count($testProviderArray) === 0) {
        throw new Exception("ERROR: Validation testsuite frameworks not found - run `git submodule update --init --recursive` to download.");
    }

    $start = microtime(true);

    foreach ($testProviderArray as $idx => $testCaseFile) {
        if (filesize($testCaseFile) > 10000) {
            continue;
        }

        $fileContents = file_get_contents($testCaseFile);

        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $maxRecursion = [];
        $definitions = [];

        $definitionResolver = new DefinitionResolver($index);
        $parser = new PhpParser\Parser();

        try {
            $document = new PhpDocument($testCaseFile, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
        } catch (\Throwable $e) {
            continue;
        }
    }

    echo "Time " . str_pad($framework, 20) . number_format(microtime(true) - $start, 3) . "s" . PHP_EOL;
}

