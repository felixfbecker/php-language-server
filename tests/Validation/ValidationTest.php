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
                continue;
            }

            $iterator = new RecursiveDirectoryIterator(__DIR__ . "/../../validation/frameworks/" . $frameworkName);
            $skipped = json_decode(file_get_contents(__DIR__ . '/skipped.json'));

            foreach (new RecursiveIteratorIterator($iterator) as $file) {
                if (strpos(\strrev((string)$file), \strrev(".php")) === 0 && !\in_array(basename((string)$file), $skipped)) {
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
    public function testDefinitionErrors($testCaseFile, $frameworkName) {
        echo "Test file: " . realpath($testCaseFile) . PHP_EOL;

        $fileContents = file_get_contents($testCaseFile);
        $expectedValues = $this->getExpectedTestValues($testCaseFile, $frameworkName, $fileContents);
        $actualValues = $this->getActualTestValues($testCaseFile, $fileContents);

        $this->assertEquals($expectedValues['definitions'], $actualValues['definitions']);

        try {
            $this->assertArraySubset((array)$expectedValues['references'], (array)$actualValues['references'], false, 'references don\'t match.');
        } catch (\Throwable $e) {
            $this->assertEquals((array)$expectedValues['references'], (array)$actualValues['references'], 'references don\'t match.');
        }
    }

    /**
     * @param $filename
     * @param $frameworkName
     * @param $fileContents
     * @return array
     */
    private function getExpectedTestValues($filename, $frameworkName, $fileContents) {
        global $parserKind;
        $parserKind = ParserKind::PHP_PARSER;

        $outputFile = $filename . '.expected.json';
        if (file_exists($outputFile)) {
            return (array)json_decode(file_get_contents($outputFile));
        }

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

        $expectedRefs = $index->references;
        $this->filterSkippedReferences($expectedRefs);
        $expectedDefs = $this->getTestValuesFromDefs($document->getDefinitions());

        $refsAndDefs = array(
            'references' => json_decode(json_encode($expectedRefs)),
            'definitions' => json_decode(json_encode($expectedDefs))
        );

        if ($frameworkName === 'broken') {
            file_put_contents($outputFile, json_encode($refsAndDefs, JSON_PRETTY_PRINT));
        }

        return $refsAndDefs;
    }

    private function getActualTestValues($filename, $fileContents): array {
        global $parserKind;
        $parserKind = ParserKind::TOLERANT_PHP_PARSER;

        $index = new Index();
        $parser = ParserResourceFactory::getParser();
        $docBlockFactory = DocBlockFactory::createInstance();
        $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);

        $document = new PhpDocument($filename, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);

        $actualRefs = $index->references;
        $this->filterSkippedReferences($actualRefs);
        $actualDefs = $this->getTestValuesFromDefs($document->getDefinitions());

        // TODO - probably a more PHP-typical way to do this. Need to compare the objects parsed from json files
        // to the real results. json_decode returns stdClass Objects, not arrays.
        return array(
            'references' => json_decode(json_encode($actualRefs)),
            'definitions' => json_decode(json_encode($actualDefs))
        );
    }

    /**
     * @param $definitions Definition[]
     * @return array|\array[]
     */
    private function getTestValuesFromDefs($definitions): array
    {
        // TODO - use reflection to read these properties
        $propertyNames = ['extends', 'isGlobal', 'isStatic', 'canBeInstantiated', 'symbolInformation', 'type', 'documentation'];

        $defsForAssert = [];
        foreach ($definitions as $definition) {
            $fqn = $definition->fqn;

            foreach ($propertyNames as $propertyName) {
                if ($propertyName === 'symbolInformation') {
                    // Range is very often different - don't check it, for now
                    unset($definition->$propertyName->location->range);
                } elseif ($propertyName === 'extends') {
                    $definition->$propertyName = $definition->$propertyName ?? [];
                } elseif ($propertyName === 'type') {
                    // Class info is not captured by json_encode. It's important for 'type'.
                    $defsForAssert[$fqn][$propertyName . '__class'] = get_class($definition->$propertyName);
                }

                $defsForAssert[$fqn][$propertyName] = $definition->$propertyName;
            }
        }

        return $defsForAssert;
    }

    private function filterSkippedReferences(&$references): void
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