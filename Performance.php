<?php

namespace LanguageServer\Tests;
require __DIR__ . '/vendor/autoload.php';

use Exception;
use LanguageServer\Index\Index;
use LanguageServer\ParserKind;
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

$start = microtime(true);

$documents = [];
foreach ($testProviderArray as $idx=>$testCaseFile) {
    if ($idx > 100) {
        break;
    }

    echo "$idx=>$testCaseFile\n";

    $fileContents = file_get_contents($testCaseFile);
        
    $docBlockFactory = DocBlockFactory::createInstance();
    $index = new Index;

    $parserKinds = [ParserKind::DIAGNOSTIC_PHP_PARSER, ParserKind::DIAGNOSTIC_TOLERANT_PHP_PARSER];

    $maxRecursion = [];
    foreach ($parserKinds as $kind) {
        global $parserKind;
        $parserKind = $kind;

        $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);
        $parser = ParserResourceFactory::getParser();


        try {
            $document = new PhpDocument($testCaseFile, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
            if ($document->getStmts() === null) {
                echo "AHHHHHHHHHH\n";
            }
            if (isset($maxRecursion[$testCaseFile]) && $maxRecursion[$testCaseFile] !== ($max = $definitionResolver::$maxRecursion)) {
                $documents[] = "$testCaseFile\n => OLD: $maxRecursion[$testCaseFile], NEW: $max";
            }
            $maxRecursion[$testCaseFile] = $definitionResolver::$maxRecursion;
//            $definitionResolver->printLogs();
        } catch (\Exception $e) {
//            echo "AAAHH\n";
            continue;
        }
    }
}

echo "------------------------------\n";
var_dump($documents);

echo "Time: " . (microtime(true) - $start) . PHP_EOL;
