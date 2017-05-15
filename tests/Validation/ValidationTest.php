<?php

declare(strict_types = 1);

namespace LanguageServer\Tests;

use Exception;
use LanguageServer\Definition;
use LanguageServer\Index\Index;
use LanguageServer\ParserKind;
use LanguageServer\PhpDocument;
use LanguageServer\TolerantDefinitionResolver;
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

$frameworksDir = realpath(__DIR__ . '/../../validation/frameworks');

class ValidationTest extends TestCase
{
    public function frameworkErrorProvider() {
        global $frameworksDir;
        $frameworks = glob($frameworksDir . '/*', GLOB_ONLYDIR);

        $testProviderArray = array();
        foreach ($frameworks as $frameworkDir) {
            $frameworkName = basename($frameworkDir);
            if ($frameworkName !== '_cases') {
                continue;
            }

            $iterator = new RecursiveDirectoryIterator($frameworkDir);
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
        $actualValues = $this->getActualTestValues($testCaseFile, $fileContents);

        $outputFile = getExpectedValuesFile($testCaseFile);
        if (!file_exists($outputFile)) {
            file_put_contents(json_encode($actualValues, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
        }

        $expectedValues = (array)json_decode(file_get_contents($outputFile));

        try {
            $this->assertEquals($expectedValues['definitions'], $actualValues['definitions']);

            try {
                $this->assertArraySubset((array)$expectedValues['references'], (array)$actualValues['references'], false, 'references don\'t match.');
            } catch (\Throwable $e) {
                $this->assertEquals((array)$expectedValues['references'], (array)$actualValues['references'], 'references don\'t match.');
            }
        } catch (\Throwable $e) {
            $outputFile = getExpectedValuesFile($testCaseFile);
            if ($frameworkName === '_cases') {
                file_put_contents($outputFile, json_encode($actualValues, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
            }

            throw $e;
        }
    }

    private function getActualTestValues($filename, $fileContents): array {
        global $frameworksDir;

        $index = new Index();
        $parser = new Tolerant\Parser();
        $docBlockFactory = DocBlockFactory::createInstance();
        $definitionResolver = new TolerantDefinitionResolver($index);

        $document = new PhpDocument($filename, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);

        $actualRefs = $index->references;
        $this->filterSkippedReferences($actualRefs);
        $actualDefs = $this->getTestValuesFromDefs($document->getDefinitions());

        // TODO - there's probably a more PHP-typical way to do this. Need to compare the objects parsed from json files
        // to the real results. json_decode returns stdClass Objects, not arrays.
        $refsAndDefs = array(
            'references' => json_decode(json_encode($actualRefs)),
            'definitions' => json_decode(json_encode($actualDefs))
        );

        // Turn references into relative paths
        foreach ($refsAndDefs['references'] as $key => $list) {
            $fixedPathRefs = array_map(function($ref) {
                global $frameworksDir;
                return str_replace($frameworksDir, '.', $ref);
            }, $list);

            $refsAndDefs['references']->$key = $fixedPathRefs;
        }

        // Turn def locations into relative paths
        foreach ($refsAndDefs['definitions'] as $key => $def) {
            if ($def !== null && $def->symbolInformation !== null &&
                $def->symbolInformation->location !== null && $def->symbolInformation->location->uri !== null) {
                $def->symbolInformation->location->uri = str_replace($frameworksDir, '.', $def->symbolInformation->location->uri);
            }
        }

        return $refsAndDefs;
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

        foreach ($references as $key => $value) {
            foreach ($skipped as $s) {
                if (strpos($key, $s) !== false) {
                    unset($references[$key]);
                }
            }
        }
    }
}

function getExpectedValuesFile($testCaseFile): string {
    return $testCaseFile . '.expected.json';
}
