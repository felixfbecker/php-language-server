<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use PHPUnit\Framework\TestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, PhpDocumentLoader, CompletionProvider, DefinitionResolver};
use LanguageServer\Index\{Index, ProjectIndex, DependenciesIndex, GlobalIndex, StubsIndex};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Protocol\{
    TextDocumentIdentifier,
    TextEdit,
    Range,
    Position,
    ClientCapabilities,
    CompletionList,
    CompletionItem,
    CompletionItemKind
};
use function LanguageServer\pathToUri;

class CompletionTest extends TestCase
{
    /**
     * @var Server\TextDocument
     */
    private $textDocument;

    /**
     * @var PhpDocumentLoader
     */
    private $loader;

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $projectIndex = new ProjectIndex(new Index, new DependenciesIndex);
        $definitionResolver = new DefinitionResolver($projectIndex);
        $contentRetriever = new FileSystemContentRetriever;
        $this->loader = new PhpDocumentLoader($contentRetriever, $projectIndex, $definitionResolver);
        $this->loader->load(pathToUri(__DIR__ . '/../../../fixtures/global_symbols.php'))->wait();
        $this->loader->load(pathToUri(__DIR__ . '/../../../fixtures/symbols.php'))->wait();
        $this->textDocument = new Server\TextDocument($this->loader, $definitionResolver, $client, $projectIndex);
    }

    public function testPropertyAndMethodWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/property_with_prefix.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(3, 7)
        )->wait();
        $this->assertEquals(new CompletionList([
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
        ], true), $items);
    }

    public function testPropertyAndMethodWithoutPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/property.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(3, 6)
        )->wait();
        $this->assertEquals(new CompletionList([
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
        ], true), $items);
    }

    public function testVariable()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/variable.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(8, 5)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                '$var',
                CompletionItemKind::VARIABLE,
                'int',
                null,
                null,
                null,
                null,
                new TextEdit(new Range(new Position(8, 5), new Position(8, 5)), 'var')
            ),
            new CompletionItem(
                '$param',
                CompletionItemKind::VARIABLE,
                'string|null',
                'A parameter',
                null,
                null,
                null,
                new TextEdit(new Range(new Position(8, 5), new Position(8, 5)), 'param')
            )
        ], true), $items);
    }

    public function testVariableWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/variable_with_prefix.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(8, 6)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                '$param',
                CompletionItemKind::VARIABLE,
                'string|null',
                'A parameter',
                null,
                null,
                null,
                new TextEdit(new Range(new Position(8, 6), new Position(8, 6)), 'aram')
            )
        ], true), $items);
    }

    public function testNewInNamespace()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/used_new.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 10)
        )->wait();
        $this->assertEquals(new CompletionList([
            // Global TestClass definition (inserted as \TestClass)
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                null,
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.' . "\n\n" .
                'Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud' . "\n" .
                'laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam' . "\n" .
                'veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat' . "\n" .
                'consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem' . "\n" .
                'sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.',
                null,
                null,
                '\TestClass'
            ),
            new CompletionItem(
                'ChildClass',
                CompletionItemKind::CLASS_,
                null,
                null,
                null,
                null,
                '\ChildClass'
            ),
            // Namespaced, `use`d TestClass definition (inserted as TestClass)
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                'TestNamespace',
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.' . "\n\n" .
                'Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud' . "\n" .
                'laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam' . "\n" .
                'veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat' . "\n" .
                'consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem' . "\n" .
                'sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.',
                null,
                null,
                'TestClass'
            ),
            new CompletionItem(
                'ChildClass',
                CompletionItemKind::CLASS_,
                'TestNamespace',
                null,
                null,
                null,
                '\TestNamespace\ChildClass'
            ),
            new CompletionItem(
                'Example',
                CompletionItemKind::CLASS_,
                'TestNamespace',
                null,
                null,
                null,
                '\TestNamespace\Example'
            )
        ], true), $items);
    }

    public function testUsedClass()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/used_class.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 5)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                'TestNamespace',
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.' . "\n\n" .
                'Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud' . "\n" .
                'laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam' . "\n" .
                'veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat' . "\n" .
                'consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem' . "\n" .
                'sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.'
            )
        ], true), $items);
    }

    public function testStaticPropertyWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/static_property_with_prefix.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 14)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                'staticTestProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass[]',
                'Lorem excepteur officia sit anim velit veniam enim.',
                null,
                null,
                '$staticTestProperty'
            )
        ], true), $items);
    }

    public function testStaticWithoutPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/static.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 11)
        )->wait();
        $this->assertEquals(new CompletionList([
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
                'Lorem excepteur officia sit anim velit veniam enim.',
                null,
                null,
                '$staticTestProperty'
            ),
            new CompletionItem(
                'staticTestMethod',
                CompletionItemKind::METHOD,
                'mixed', // Method return type
                'Do magna consequat veniam minim proident eiusmod incididunt aute proident.'
            )
        ], true), $items);
    }

    public function testStaticMethodWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/static_method_with_prefix.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 13)
        )->wait();
        $this->assertEquals(new CompletionList([
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
                'Lorem excepteur officia sit anim velit veniam enim.',
                null,
                null,
                '$staticTestProperty'
            ),
            new CompletionItem(
                'staticTestMethod',
                CompletionItemKind::METHOD,
                'mixed',
                'Do magna consequat veniam minim proident eiusmod incididunt aute proident.'
            )
        ], true), $items);
    }

    public function testClassConstWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/class_const_with_prefix.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 13)
        )->wait();
        $this->assertEquals(new CompletionList([
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
                'Lorem excepteur officia sit anim velit veniam enim.',
                null,
                null,
                '$staticTestProperty'
            ),
            new CompletionItem(
                'staticTestMethod',
                CompletionItemKind::METHOD,
                'mixed',
                'Do magna consequat veniam minim proident eiusmod incididunt aute proident.'
            )
        ], true), $items);
    }

    public function testFullyQualifiedClass()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/fully_qualified_class.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(6, 6)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                'TestClass',
                CompletionItemKind::CLASS_,
                null,
                'Pariatur ut laborum tempor voluptate consequat ea deserunt.' . "\n\n" .
                'Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud' . "\n" .
                'laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam' . "\n" .
                'veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat' . "\n" .
                'consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem' . "\n" .
                'sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.',
                null,
                null,
                'TestClass'
            )
        ], true), $items);
    }

    public function testKeywords()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/keywords.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(2, 1)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem('class', CompletionItemKind::KEYWORD, null, null, null, null, 'class '),
            new CompletionItem('clone', CompletionItemKind::KEYWORD, null, null, null, null, 'clone ')
        ], true), $items);
    }

    public function testHtmlWithoutPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/html.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(0, 0)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                '<?php',
                CompletionItemKind::KEYWORD,
                null,
                null,
                null,
                null,
                null,
                new TextEdit(new Range(new Position(0, 0), new Position(0, 0)), '<?php')
            )
        ], true), $items);
    }

    public function testHtmlWithPrefix()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/html_with_prefix.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(0, 1)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                '<?php',
                CompletionItemKind::KEYWORD,
                null,
                null,
                null,
                null,
                null,
                new TextEdit(new Range(new Position(0, 1), new Position(0, 1)), '?php')
            )
        ], true), $items);
    }

    public function testNamespace()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/namespace.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(4, 6)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                'SomeNamespace',
                CompletionItemKind::MODULE,
                null,
                null,
                null,
                null,
                'SomeNamespace'
            )
        ], true), $items);
    }

    public function testBarePhp()
    {
        $completionUri = pathToUri(__DIR__ . '/../../../fixtures/completion/bare_php.php');
        $this->loader->open($completionUri, file_get_contents($completionUri));
        $items = $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position(4, 8)
        )->wait();
        $this->assertEquals(new CompletionList([
            new CompletionItem(
                '$abc2',
                CompletionItemKind::VARIABLE,
                'int',
                null,
                null,
                null,
                null,
                new TextEdit(new Range(new Position(4, 8), new Position(4, 8)), 'c2')
            ),
            new CompletionItem(
                '$abc',
                CompletionItemKind::VARIABLE,
                'int',
                null,
                null,
                null,
                null,
                new TextEdit(new Range(new Position(4, 8), new Position(4, 8)), 'c')
            )
        ], true), $items);
    }
}
