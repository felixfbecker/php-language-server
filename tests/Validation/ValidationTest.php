<?php

declare(strict_types = 1);

namespace LanguageServer\Tests;

use Exception;
use LanguageServer\Definition;
use LanguageServer\Index\Index;
use LanguageServer\ParserKind;
use LanguageServer\ParserResourceFactory;
use LanguageServer\PhpDocument;
use phpDocumentor\Reflection\DocBlock;
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
        $frameworks = glob(__DIR__ . "/../../validation/frameworks/*", GLOB_ONLYDIR);

        $testProviderArray = array();
        foreach ($frameworks as $frameworkDir) {
            $frameworkName = basename($frameworkDir);
            if ($frameworkName !== "broken") {
//                continue;
            }

            $iterator = new RecursiveDirectoryIterator(__DIR__ . "/../../validation/frameworks/" . $frameworkName);

            foreach (new RecursiveIteratorIterator($iterator) as $file) {
                if (strpos(\strrev((string)$file), \strrev(".php")) === 0) {
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
     * @group validation
     * @dataProvider frameworkErrorProvider
     * @param $testCaseFile
     * @param $frameworkName
     */
    public function testFrameworkErrors($testCaseFile, $frameworkName) {
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
    }

    /**
     * @group validation
     * @dataProvider frameworkErrorProvider
     * @param $testCaseFile
     * @param $frameworkName
     */
    public function testDefinitionErrors($testCaseFile, $frameworkName) {
        echo PHP_EOL . realpath($testCaseFile) . PHP_EOL;

        $fileContents = file_get_contents($testCaseFile);
        [$expectedDefinitions, $expectedReferences] = $this->getExpectedDefinitionsAndReferences($testCaseFile, $frameworkName, $fileContents);
        [$actualDefinitions, $actualReferences] = $this->getActualDefinitionsAndReferences($testCaseFile, $fileContents);

        $this->filterSkippedReferences($expectedReferences);
        $this->filterSkippedReferences($actualReferences);

        $expectedValues = $this->getValuesFromDefinitionsAndReferences($expectedDefinitions, $expectedReferences);
        $actualValues = $this->getValuesFromDefinitionsAndReferences($actualDefinitions, $actualReferences);

        foreach ($expectedValues as $name => $expectedValue) {
            $actualValue = $actualValues[$name];

            if ($name === 'references') {
                try {
                    $this->assertArraySubset($expectedValue, $actualValue, false, 'references don\'t match.');
                } catch (\Throwable $e) {
                    $this->assertEquals($expectedValue, $actualValue, 'references don\'t match.');
                }
                continue;
            }

            $this->assertEquals($expectedValue, $actualValue, "$name did not match.");
        }
    }

    /**
     * @param $filename
     * @param $fileContents
     * @return array<Definition[], string[][]>
     */
    private function getExpectedDefinitionsAndReferences($filename, $frameworkName, $fileContents) {
//        $outputFile = $filename . '.expected';
//        if (file_exists($outputFile)) {
//            return json_decode(file_get_contents($outputFile));
//        }

        global $parserKind;
        $parserKind = ParserKind::PHP_PARSER;

        $index = new Index();
        $parser = ParserResourceFactory::getParser();
        $docBlockFactory = DocBlockFactory::createInstance();
        $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);

        try {
            $document = new PhpDocument($filename, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
        } catch (\Throwable $e) {
            $this->markTestSkipped('Baseline parser failed: '. $e->getTraceAsString());
        }

        if ($document->getStmts() === null) {
            $this->markTestSkipped('Baseline parser failed: null AST');
        }

        $defsAndRefs = [$document->getDefinitions(), $index->references];
//        if ($frameworkName === 'broken') {
//            file_put_contents($outputFile, json_encode($defsAndRefs, JSON_PRETTY_PRINT));
//        }
//        }

        return $defsAndRefs;
    }

    private function getActualDefinitionsAndReferences($filename, $fileContents) {
        global $parserKind;
        $parserKind = ParserKind::TOLERANT_PHP_PARSER;

        $index = new Index();
        $parser = ParserResourceFactory::getParser();
        $docBlockFactory = DocBlockFactory::createInstance();
        $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);

        $document = new PhpDocument($filename, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);

        return [$document->getDefinitions(), $index->references];
    }

    /**
     * @param $expectedDefinitions
     * @param $expectedReferences
     * @return array|\array[]
     * @internal param $propertyNames
     */
    private function getValuesFromDefinitionsAndReferences($expectedDefinitions, $expectedReferences): array
    {
        // TODO - use reflection to read these properties
        $propertyNames = ['extends', 'isGlobal', 'isStatic', 'canBeInstantiated', 'symbolInformation', 'type', 'documentation'];

        $expectedValues = [];
        foreach ($expectedDefinitions as $expectedDefinition) {
            $fqn = $expectedDefinition->fqn;
            $expectedValues['$def->fqn'][] = $fqn;

            foreach ($propertyNames as $propertyName) {
                if ($propertyName === 'symbolInformation') {
                    unset($expectedDefinition->$propertyName->location->range);
                } elseif ($propertyName === 'extends') {
                    $expectedDefinition->$propertyName = $expectedDefinition->$propertyName ?? [];
                }
                $expectedValues['$def->' . $propertyName][$fqn] = $expectedDefinition->$propertyName;
            }
        }

        $expectedValues['references'] = $expectedReferences;
        return $expectedValues;
    }

    private function filterSkippedReferences(&$references)
    {
        $skipped = [
            'false', 'true', 'null', 'FALSE', 'TRUE', 'NULL',
            '__', // magic constants are treated as normal constants
            'Exception', 'Error', // catch exception types missing from old definition resolver
            'Trait', // use Trait references are missing from old definition resolve
            '->tableAlias', '->realField', '->field', '->first_name', '->last_name', '->quoteMatch', '->idCol', '->timeCol', '->dataCol',
            'pathToUri', 'uriToPath' // group function use declarations are broken in old definition resolver
        ];

        foreach ($references as $key=>$value) {
            foreach ($skipped as $s) {
                if (strpos($key, $s) !== false) {
                    unset($references[$key]);
                }
            }
        }
    }
}