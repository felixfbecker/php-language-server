<?php

declare(strict_types = 1);

namespace LanguageServer\Tests;

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

class ValidationTest extends TestCase
{
    public function frameworkErrorProvider() {
        $totalSize = 0;
        $frameworks = glob(__DIR__ . "/../../validation/frameworks/*", GLOB_ONLYDIR);

        $testProviderArray = array();
        foreach ($frameworks as $frameworkDir) {
            $frameworkName = basename($frameworkDir);
            $iterator = new RecursiveDirectoryIterator(__DIR__ . "/../../validation/frameworks/" . $frameworkName);

            foreach (new RecursiveIteratorIterator($iterator) as $file) {
                if (strpos((string)$file, ".php") !== false) {
                    $totalSize += $file->getSize();
                    $testProviderArray[$frameworkName . "::" . $file->getBasename()] = [$file->getPathname(), $frameworkName];
                }
            }
        }
        if (count($testProviderArray) === 0) {
            throw new Exception("ERROR: Validation testsuite frameworks not found - run `git submodule update --init --recursive` to download.");
        }
        return $testProviderArray;
    }

    /**
     * @dataProvider frameworkErrorProvider
     */
    public function testFramworkErrors($testCaseFile, $frameworkName) {
        $fileContents = file_get_contents($testCaseFile);
        
        $parser = ParserResourceFactory::getParser();
        $docBlockFactory = DocBlockFactory::createInstance();
        $index = new Index;
        $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);

        $directory = __DIR__ . "/output/$frameworkName/";
        $outFile = $directory . basename($testCaseFile);

        try {
            $document = new PhpDocument($testCaseFile, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
        } catch (\Exception $e) {
            if (!file_exists($dir = __DIR__ . "/output")) {
                mkdir($dir);
            }
            if (!file_exists($directory)) {
                mkdir($directory);
            }
            file_put_contents($outFile, $fileContents);
            $this->fail((string)$e);
        }

        if (file_exists($outFile)) {
            unlink($outFile);
        }
        // echo json_encode($parser->getErrors($sourceFile));
    }
}