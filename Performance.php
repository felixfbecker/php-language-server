<?php

namespace LanguageServer\Tests;
require __DIR__ . '/vendor/autoload.php';

use Exception;
use LanguageServer\Index\Index;
use LanguageServer\ParserResourceFactory;
use LanguageServer\PhpDocument;
use phpDocumentor\Reflection\DocBlockFactory;
use PHPUnit\Framework\TestCase;
use LanguageServer\ClientHandler;
use LanguageServer\Protocol\Message;
use AdvancedJsonRpc;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Sabre\Event\Loop;

$totalSize = 0;
$testProviderArray = array();

$iterator = new RecursiveDirectoryIterator(__DIR__ . "/validation/frameworks/WordPress");

foreach (new RecursiveIteratorIterator($iterator) as $file) {
    if (strpos((string)$file, ".php") !== false) {
        $totalSize += $file->getSize();
        $testProviderArray[] = $file->getPathname();
    }
}

if (count($testProviderArray) === 0) {
    throw new Exception("ERROR: Validation testsuite frameworks not found - run `git submodule update --init --recursive` to download.");
}

foreach ($testProviderArray as $idx=>$testCaseFile) {
    if ($idx > 10) {
        exit();
    }

    echo "$idx\n";

    $fileContents = file_get_contents($testCaseFile);
        
        $parser = ParserResourceFactory::getParser();
        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);

        try {
            $document = new PhpDocument($testCaseFile, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
        } catch (\Exception $e) {
            echo "AAAHH\n";
            continue;
        }
}

