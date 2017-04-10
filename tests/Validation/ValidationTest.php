<?php

declare(strict_types = 1);

namespace LanguageServer\Tests;

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
use Microsoft\PhpParser as Tolerant;

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
                if (strpos((string)$file, ".php") !== false && strpos((string)$file, "drupal") === false) {
                    if ($file->getSize() < 100000) {
                        $testProviderArray[$frameworkName . "::" . $file->getBasename()] = [$file->getPathname(), $frameworkName];
                    }
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

        $this->assertNotNull($document->getStmts());

        if (file_exists($outFile)) {
            unlink($outFile);
        }
        // echo json_encode($parser->getErrors($sourceFile));
    }

    /**
     * @dataProvider frameworkErrorProvider
     */
    public function testDefinitionErrors($testCaseFile, $frameworkName) {
        $fileContents = file_get_contents($testCaseFile);
        echo "$testCaseFile\n";

        $parserKinds = [ParserKind::DIAGNOSTIC_PHP_PARSER, ParserKind::DIAGNOSTIC_TOLERANT_PHP_PARSER];
        $maxRecursion = [];
        $definitions = [];

        foreach ($parserKinds as $kind) {
            global $parserKind;
            $parserKind = $kind;

            $index = new Index;
            $docBlockFactory = DocBlockFactory::createInstance();

            $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);
            $parser = ParserResourceFactory::getParser();

            try {
                $document = new PhpDocument($testCaseFile, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
            } catch (\Exception $e) {
                continue;
            }

            $fqns = [];
            foreach ($document->getDefinitions() as $defn) {
                $fqns[] = $defn->fqn;
            }

            if (isset($definitions[$testCaseFile])) {
                var_dump($definitions[$testCaseFile]);
                $this->assertEquals($definitions[$testCaseFile], $fqns);
            }

            $definitions[$testCaseFile] = $fqns;
            $maxRecursion[$testCaseFile] = $definitionResolver::$maxRecursion;
        }
    }
}