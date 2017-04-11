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
            if ($frameworkName !== "wordpress") {
                continue;
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

    private $index = [];

    private function getIndex($kind) {
        if (!isset($this->index[$kind])) {
            $this->index[$kind] = new Index();
        }
        return $this->index[$kind];
    }

    /**
     * @group validation
     * @dataProvider frameworkErrorProvider
     */
    public function testDefinitionErrors($testCaseFile, $frameworkName) {
        $fileContents = file_get_contents($testCaseFile);
        echo "$testCaseFile\n";

        $parserKinds = [ParserKind::DIAGNOSTIC_PHP_PARSER, ParserKind::DIAGNOSTIC_TOLERANT_PHP_PARSER];
        $maxRecursion = [];
        $definitions = [];
        $instantiated = [];
        $types = [];
        $symbolInfo = [];
        $extend = [];
        $isGlobal = [];
        $documentation = [];
        $isStatic = [];

        foreach ($parserKinds as $kind) {
            global $parserKind;
            $parserKind = $kind;

            $index = $this->getIndex($kind);
            $docBlockFactory = DocBlockFactory::createInstance();

            $definitionResolver = ParserResourceFactory::getDefinitionResolver($index);
            $parser = ParserResourceFactory::getParser();

            try {
                $document = new PhpDocument($testCaseFile, $fileContents, $index, $parser, $docBlockFactory, $definitionResolver);
            } catch (\Exception $e) {
                continue;
            }

            if ($document->getStmts() === null) {
                $this->markTestSkipped("null AST");
            }

            $fqns = [];
            $currentTypes = [];
            $canBeInstantiated = [];
            $symbols = [];
            $extends = [];
            $global = [];
            $docs = [];
            $static = [];
            foreach ($document->getDefinitions() as $defn) {
                $fqns[] = $defn->fqn;
                $currentTypes[$defn->fqn] = $defn->type;
                $canBeInstantiated[$defn->fqn] = $defn->canBeInstantiated;

                $defn->symbolInformation->location = null;
                $symbols[$defn->fqn] = $defn->symbolInformation;

                $extends[$defn->fqn] = $defn->extends;
                $global[$defn->fqn] = $defn->isGlobal;
                $docs[$defn->fqn] = $defn->documentation;
                $static[$defn->fqn] = $defn->isStatic;
            }

            if (isset($definitions[$testCaseFile])) {
                $this->assertEquals($definitions[$testCaseFile], $fqns, 'defn->fqn does not match');
//                $this->assertEquals($types[$testCaseFile], $currentTypes, "defn->type does not match");
                $this->assertEquals($instantiated[$testCaseFile], $canBeInstantiated, "defn->canBeInstantiated does not match");
                $this->assertEquals($extend[$testCaseFile], $extends, 'defn->extends does not match');
                $this->assertEquals($isGlobal[$testCaseFile], $global, 'defn->isGlobal does not match');
                $this->assertEquals($documentation[$testCaseFile], $docs, 'defn->documentation does not match');
                $this->assertEquals($isStatic[$testCaseFile], $static, 'defn->isStatic does not match');

                $this->assertEquals($symbolInfo[$testCaseFile], $symbols, "defn->symbolInformation does not match");
                $this->assertEquals($this->getIndex($parserKinds[0])->references, $this->getIndex($parserKinds[1])->references);
            }

            $definitions[$testCaseFile] = $fqns;
            $types[$testCaseFile] = $currentTypes;
            $instantiated[$testCaseFile] = $canBeInstantiated;
            $symbolInfo[$testCaseFile] = $symbols;
            $extend[$testCaseFile] = $extends;
            $isGlobal[$testCaseFile] = $global;
            $documentation[$testCaseFile] = $docs;
            $isStatic[$testCaseFile] = $static;

            $maxRecursion[$testCaseFile] = $definitionResolver::$maxRecursion;
        }
    }
}