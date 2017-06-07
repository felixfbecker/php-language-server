<?php

declare(strict_types = 1);

namespace LanguageServer\Tests;

use Exception;
use LanguageServer\Definition;
use LanguageServer\Index\Index;
use LanguageServer\ParserKind;
use LanguageServer\PhpDocument;
use LanguageServer\DefinitionResolver;
use phpDocumentor\Reflection\DocBlock;
use phpDocumentor\Reflection\DocBlockFactory;
use PHPUnit\Framework\TestCase;
use LanguageServer\ClientHandler;
use LanguageServer\Protocol\Message;
use AdvancedJsonRpc;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Sabre\Event\Loop;
use Microsoft\PhpParser;

class ValidationTest extends TestCase
{
    public function validationTestProvider()
    {
        $testProviderArray = array();
        $testCasesDir = realpath(__DIR__ . '/cases');

        $iterator = new RecursiveDirectoryIterator($testCasesDir);
        $disabled = json_decode(file_get_contents(__DIR__ . '/disabled.json'));

        foreach (new RecursiveIteratorIterator($iterator) as $file) {
            if (strpos(\strrev((string)$file), \strrev(".php")) === 0 && !\in_array(basename((string)$file), $disabled)) {
                if ($file->getSize() < 100000) {
                    $testProviderArray[] = [$file->getPathname()];
                }
            }
        }

        return $testProviderArray;
    }

    /**
     * This test loads the test cases specified in .php files under cases/ and looks at the whole set of 
     * Definitions and References produced. It reads the expected results from associated .json files 
     * and compares to the actual result. If they don't match, the test fails and it writes the new baseline
     * to the .json file.
     * @group validation
     * @dataProvider validationTestProvider
     * @param $testCaseFile
     */
    public function testDefinitionsAndReferences($testCaseFile)
    {
        $fileContents = file_get_contents($testCaseFile);
        $actualValues = $this->getActualTestValues($testCaseFile, $fileContents);

        $outputFile = getExpectedValuesFile($testCaseFile);
        if (!file_exists($outputFile)) {
            file_put_contents($outputFile, json_encode($actualValues, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));
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
            file_put_contents($outputFile, json_encode($actualValues, JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));

            throw $e;
        }
    }

    private function getActualTestValues($filename, $fileContents): array
    {
        $index = new Index();
        $parser = new PhpParser\Parser();
        $docBlockFactory = DocBlockFactory::createInstance();
        $definitionResolver = new DefinitionResolver($index);

        $document = new PhpDocument($filename, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);

        $actualRefs = $index->getReferences();
        $this->filterSkippedReferences($actualRefs);
        $actualDefs = $this->getTestValuesFromDefs($document->getDefinitions());

        // TODO - there's probably a more PHP-typical way to do this. Need to compare the objects parsed from json files
        // to the real results. json_decode returns stdClass Objects, not arrays.
        $refsAndDefs = array(
            'references' => json_decode(json_encode($actualRefs)),
            'definitions' => json_decode(json_encode($actualDefs))
        );

        // Turn references into relative paths
        $testCasesDir = realpath(__DIR__ . '/cases');
        foreach ($refsAndDefs['references'] as $key => $list) {
            $fixedPathRefs = array_map(function ($ref) use ($testCasesDir) {
                return str_replace($testCasesDir, '.', $ref);
            }, $list);

            $refsAndDefs['references']->$key = $fixedPathRefs;
        }

        // Turn def locations into relative paths
        foreach ($refsAndDefs['definitions'] as $key => $def) {
            if ($def !== null && $def->symbolInformation !== null &&
                $def->symbolInformation->location !== null && $def->symbolInformation->location->uri !== null) {
                $def->symbolInformation->location->uri = str_replace($testCasesDir, '.', $def->symbolInformation->location->uri);
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
                } elseif ($propertyName === 'type' && $definition->type !== null) {
                    // Class info is not captured by json_encode. It's important for 'type'.
                    $defsForAssert[$fqn]['type__class'] = get_class($definition->type);
                }

                $defsForAssert[$fqn][$propertyName] = $definition->$propertyName;
            }
        }

        return $defsForAssert;
    }

    /**
     * @return void
     */
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

        foreach ($references as $key => $value) {
            foreach ($skipped as $s) {
                if (strpos($key, $s) !== false) {
                    unset($references[$key]);
                }
            }
        }
    }
}

function getExpectedValuesFile($testCaseFile): string
{
    return $testCaseFile . '.expected.json';
}
