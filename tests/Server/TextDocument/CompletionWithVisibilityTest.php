<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument;

use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\DefinitionResolver;
use LanguageServer\Index\DependenciesIndex;
use LanguageServer\Index\Index;
use LanguageServer\Index\ProjectIndex;
use LanguageServer\LanguageClient;
use LanguageServer\PhpDocumentLoader;
use LanguageServer\Server\TextDocument;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServerProtocol\CompletionItem;
use LanguageServerProtocol\CompletionItemKind;
use LanguageServerProtocol\CompletionList;
use LanguageServerProtocol\Position;
use LanguageServerProtocol\TextDocumentIdentifier;
use PHPUnit\Framework\TestCase;
use function file_get_contents;
use function LanguageServer\pathToUri;

/**
 * Description of CompletionWithVisibilityTest
 *
 * @author Gabriel Noé <jnoe@itnow.externos.es>
 */
class CompletionWithVisibilityTest extends TestCase
{

    /**
     * @var TextDocument
     */
    private $textDocument;

    /**
     * @var PhpDocumentLoader
     */
    private $loader;

    /**
     *
     * @var string
     */
    private $fixturesPath;

    public function __construct($name = null, array $data = array(), $dataName = '')
    {
        parent::__construct($name, $data, $dataName);
        $this->fixturesPath = __DIR__ . '/../../../fixtures';
    }

    public function setUp()
    {
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $projectIndex = new ProjectIndex(new Index, new DependenciesIndex);
        $definitionResolver = new DefinitionResolver($projectIndex);
        $contentRetriever = new FileSystemContentRetriever;
        $this->loader = new PhpDocumentLoader($contentRetriever, $projectIndex, $definitionResolver);
        $this->loader->load(pathToUri($this->fixturesPath . '/visibility_class.php'))->wait();
        $this->textDocument = new TextDocument($this->loader, $definitionResolver, $client, $projectIndex);
    }

    /**
     * Can access only to public properties and methods
     */
    public function testVisibilityFromCall()
    {
        $items = $this->getCompletion('/completion/property.php', 3, 6);
        // doesn't contain any of these properties and methods
        $this->assertCompletionsListSubsetNotContains(new CompletionList([
            new CompletionItem(
                'privateProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass',
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'privateTestMethod',
                CompletionItemKind::METHOD,
                'mixed' // Return type of the method
            ),
            new CompletionItem(
                'protectedProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass',
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'protectedTestMethod',
                CompletionItemKind::METHOD,
                'mixed' // Return type of the method
            )
            ], true), $items);
    }

    /**
     * From a Child class only public and protected properties and methods are
     * visible
     *
     */
    public function testVisibilityInsideADescendantClassMethod()
    {
        $items = $this->getCompletion('/completion/child_class_visibility.php', 6, 16);
        // doesn't contain any of these properties and methods
        $this->assertCompletionsListSubsetNotContains(new CompletionList([
            new CompletionItem(
                'privateProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass',
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'privateTestMethod',
                CompletionItemKind::METHOD,
                'mixed' // Return type of the method
            )
            ], true), $items);
    }

    public function testVisibilityInsideClassMethod()
    {
        $items = $this->getCompletion('/visibility_class.php', 64, 15);
        // can see all properties and methods
        $this->assertCompletionsListSubset(new CompletionList([
            new CompletionItem(
                'privateProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass',
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'privateTestMethod',
                CompletionItemKind::METHOD,
                'mixed' // Return type of the method
            ),
            new CompletionItem(
                'protectedProperty',
                CompletionItemKind::PROPERTY,
                '\TestClass',
                'Reprehenderit magna velit mollit ipsum do.'
            ),
            new CompletionItem(
                'protectedTestMethod',
                CompletionItemKind::METHOD,
                'mixed' // Return type of the method
            ),
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

    /**
     *
     * @param string $fixtureFile
     * @param int $line
     * @param int $char
     * @return CompletionList
     */
    private function getCompletion(string $fixtureFile, int $line, int $char)
    {
        $completionUri = pathToUri($this->fixturesPath . $fixtureFile);
        $this->loader->open($completionUri, file_get_contents($completionUri));
        return $this->textDocument->completion(
            new TextDocumentIdentifier($completionUri),
            new Position($line, $char)
        )->wait();
    }

    private function assertCompletionsListSubset(CompletionList $subsetList, CompletionList $list)
    {
        foreach ($subsetList->items as $expectedItem) {
            $this->assertContains($expectedItem, $list->items, null, null, false);
        }

        $this->assertEquals($subsetList->isIncomplete, $list->isIncomplete);
    }

    private function assertCompletionsListSubsetNotContains(CompletionList $subsetList, CompletionList $list)
    {
        foreach ($subsetList->items as $expectedItem) {
            $this->assertNotContains($expectedItem, $list->items, null, null, false);
        }
        $this->assertEquals($subsetList->isIncomplete, $list->isIncomplete);
    }
}
