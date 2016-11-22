<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, Project};
use LanguageServer\Protocol\{TextDocumentIdentifier, Position, ClientCapabilities, CompletionItem, CompletionItemKind};
use function LanguageServer\pathToUri;

class CompletionTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    /**
     * @var Project
     */
    private $project;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $this->project = new Project($client, new ClientCapabilities);
        $this->project->loadDocument(pathToUri(__DIR__ . '/../../../fixtures/global_symbols.php'))->wait();
        $this->project->loadDocument(pathToUri(__DIR__ . '/../../../fixtures/symbols.php'))->wait();
        $this->textDocument = new Server\TextDocument($this->project, $client);
    }

    public function testPropertyAndMethodWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/property_with_prefix.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(3, 7)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'testProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass', // Type of the property
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'testMethod',
                CompletionItemKind::METHOD,
                '\TestClass', // Return type of the method
                'Non culpa nostrud mollit esse sunt laboris in irure ullamco cupidatat amet.'
            )
        ], $items);
    }

    public function testPropertyAndMethodWithoutPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/property.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(3, 6)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'testProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass', // Type of the property
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'testMethod',
                CompletionItemKind::METHOD,
                '\TestClass', // Return type of the method
                'Non culpa nostrud mollit esse sunt laboris in irure ullamco cupidatat amet.'
            )
        ], $items);
    }

    public function testVariable()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/variable.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(8, 5)
        )->wait();
        $this->assertEquals([
            new CompletionItem('$var', CompletionItemKind::VARIABLE, 'int'),
            new CompletionItem('$param', CompletionItemKind::VARIABLE, 'string|null', 'A parameter')
        ], $items);
    }

    public function testVariableWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/variable_with_prefix.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(8, 5)
        )->wait();
        $this->assertEquals([
            new CompletionItem('$param', CompletionItemKind::VARIABLE, 'string|null', 'A parameter')
        ], $items);
    }

    public function testNewInNamespace()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/used_new.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 10)
        )->wait();
        $this->assertEquals([
            // Global TestClass definition (inserted as \TestClass)
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                null,
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.',
                null,
                null,
                '\TestClass'
            ),
            // Namespaced, `use`d TestClass definition (inserted as TestClass)
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                'TestNamespace',
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.',
                null,
                null,
                'TestClass'
            ),
        ], $items);
    }

    public function testUsedClass()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/used_class.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 5)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                'TestNamespace',
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.'
            )
        ], $items);
    }

    public function testStaticPropertyWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/static_property_with_prefix.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 14)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'staticTestProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass[]',
                'Lorem excepteur officia sit anim velit veniam enim.'
            )
        ], $items);
    }

    public function testStaticWithoutPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/static.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 11)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'TEST_CLASS_CONST',
                CompletionItemKind::VARIABLE,
                'int',
                'Anim labore veniam consectetur laboris minim quis aute aute esse nulla ad.'
            ),
            new CompletionItem(
                'staticTestProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass[]',
                'Lorem excepteur officia sit anim velit veniam enim.'
            ),
            new CompletionItem(
                'staticTestMethod',
                CompletionItemKind::METHOD,
                'mixed', // Method return type
                'Do magna consequat veniam minim proident eiusmod incididunt aute proident.'
            )
        ], $items);
    }

    public function testStaticMethodWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/static_method_with_prefix.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 13)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'staticTestMethod',
                CompletionItemKind::METHOD,
                'mixed', // Method return type
                'Do magna consequat veniam minim proident eiusmod incididunt aute proident.'
            )
        ], $items);
    }

    public function testClassConstWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/class_const_with_prefix.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 13)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'TEST_CLASS_CONST',
                CompletionItemKind::VARIABLE,
                'int',
                'Anim labore veniam consectetur laboris minim quis aute aute esse nulla ad.'
            )
        ], $items);
    }

    public function testFullyQualifiedClass()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/fully_qualified_class.php');
        $this->project->openDocument($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 6)
        )->wait();
        $this->assertEquals([
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                null,
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.',
                null,
                null,
                'TestClass'
            )
        ], $items);
    }
}
