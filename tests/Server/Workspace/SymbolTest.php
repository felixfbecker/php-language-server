<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\Workspace;

use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\{Server, Client, LanguageClient, Project, PhpDocument};
use LanguageServer\Protocol\{TextDocumentItem, TextDocumentIdentifier, SymbolInformation, SymbolKind, DiagnosticSeverity, FormattingOptions};
use AdvancedJsonRpc\{Request as RequestBody, Response as ResponseBody};
use function LanguageServer\pathToUri;

class SymbolTest extends ServerTestCase
{
    public function testEmptyQueryReturnsAllSymbols()
    {
        // Request symbols
        $result = $this->workspace->symbol('');
        $this->assertEquals([
            // Namespaced
            new SymbolInformation('TEST_CONST',         SymbolKind::CONSTANT,  $this->getDefinitionLocation('TestNamespace\\TEST_CONST'),                    'TestNamespace'),
            new SymbolInformation('TestClass',          SymbolKind::CLASS_,    $this->getDefinitionLocation('TestNamespace\\TestClass'),                     'TestNamespace'),
            new SymbolInformation('TEST_CLASS_CONST',   SymbolKind::CONSTANT,  $this->getDefinitionLocation('TestNamespace\\TestClass::TEST_CLASS_CONST'),   'TestNamespace\\TestClass'),
            new SymbolInformation('staticTestProperty', SymbolKind::FIELD,     $this->getDefinitionLocation('TestNamespace\\TestClass::staticTestProperty'), 'TestNamespace\\TestClass'),
            new SymbolInformation('testProperty',       SymbolKind::FIELD,     $this->getDefinitionLocation('TestNamespace\\TestClass::testProperty'),       'TestNamespace\\TestClass'),
            new SymbolInformation('staticTestMethod',   SymbolKind::METHOD,    $this->getDefinitionLocation('TestNamespace\\TestClass::staticTestMethod()'), 'TestNamespace\\TestClass'),
            new SymbolInformation('testMethod',         SymbolKind::METHOD,    $this->getDefinitionLocation('TestNamespace\\TestClass::testMethod()'),       'TestNamespace\\TestClass'),
            new SymbolInformation('TestTrait',          SymbolKind::CLASS_,    $this->getDefinitionLocation('TestNamespace\\TestTrait'),                     'TestNamespace'),
            new SymbolInformation('TestInterface',      SymbolKind::INTERFACE, $this->getDefinitionLocation('TestNamespace\\TestInterface'),                 'TestNamespace'),
            new SymbolInformation('test_function',      SymbolKind::FUNCTION,  $this->getDefinitionLocation('TestNamespace\\test_function()'),               'TestNamespace'),
            new SymbolInformation('whatever',           SymbolKind::FUNCTION,  $this->getDefinitionLocation('TestNamespace\\whatever()'),                    'TestNamespace'),
            // Global
            new SymbolInformation('TEST_CONST',         SymbolKind::CONSTANT,  $this->getDefinitionLocation('TEST_CONST'),                                   ''),
            new SymbolInformation('TestClass',          SymbolKind::CLASS_,    $this->getDefinitionLocation('TestClass'),                                    ''),
            new SymbolInformation('TEST_CLASS_CONST',   SymbolKind::CONSTANT,  $this->getDefinitionLocation('TestClass::TEST_CLASS_CONST'),                  'TestClass'),
            new SymbolInformation('staticTestProperty', SymbolKind::FIELD,     $this->getDefinitionLocation('TestClass::staticTestProperty'),                'TestClass'),
            new SymbolInformation('testProperty',       SymbolKind::FIELD,     $this->getDefinitionLocation('TestClass::testProperty'),                      'TestClass'),
            new SymbolInformation('staticTestMethod',   SymbolKind::METHOD,    $this->getDefinitionLocation('TestClass::staticTestMethod()'),                'TestClass'),
            new SymbolInformation('testMethod',         SymbolKind::METHOD,    $this->getDefinitionLocation('TestClass::testMethod()'),                      'TestClass'),
            new SymbolInformation('TestTrait',          SymbolKind::CLASS_,    $this->getDefinitionLocation('TestTrait'),                                    ''),
            new SymbolInformation('TestInterface',      SymbolKind::INTERFACE, $this->getDefinitionLocation('TestInterface'),                                ''),
            new SymbolInformation('test_function',      SymbolKind::FUNCTION,  $this->getDefinitionLocation('test_function()'),                              ''),
            new SymbolInformation('whatever',           SymbolKind::FUNCTION,  $this->getDefinitionLocation('whatever()'),                                   '')
        ], $result);
    }

    public function testQueryFiltersResults()
    {
        // Request symbols
        $result = $this->workspace->symbol('testmethod');
        $this->assertEquals([
            new SymbolInformation('staticTestMethod',   SymbolKind::METHOD,    $this->getDefinitionLocation('TestNamespace\\TestClass::staticTestMethod()'), 'TestNamespace\\TestClass'),
            new SymbolInformation('testMethod',         SymbolKind::METHOD,    $this->getDefinitionLocation('TestNamespace\\TestClass::testMethod()'),       'TestNamespace\\TestClass'),
            new SymbolInformation('staticTestMethod',   SymbolKind::METHOD,    $this->getDefinitionLocation('TestClass::staticTestMethod()'),                'TestClass'),
            new SymbolInformation('testMethod',         SymbolKind::METHOD,    $this->getDefinitionLocation('TestClass::testMethod()'),                      'TestClass')
        ], $result);
    }
}
