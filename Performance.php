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

$totalSize = 0;

$frameworks = ["drupal", "wordpress", "php-language-server", "tolerant-php-parser", "math-php", "symfony", "CodeIgniter", "cakephp"];

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
        if ($idx % 1000 === 0) {
            echo "$idx\n";
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

    echo "------------------------------\n";

    echo "Time [$framework]: " . (microtime(true) - $start) . PHP_EOL;
}

