<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\Workspace;

use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\{Server, LanguageClient, PhpDocumentLoader, DefinitionResolver, Options, Indexer};
use LanguageServer\Index\{ProjectIndex, StubsIndex, GlobalIndex, DependenciesIndex, Index};
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Protocol\{Position, Location, Range, ClientCapabilities, Message, MessageType};
use LanguageServer\FilesFinder\FileSystemFilesFinder;
use LanguageServer\Cache\FileSystemCache;
use LanguageServer\Server\Workspace;
use Sabre\Event\Promise;
use Exception;

class DidChangeConfigurationTest extends ServerTestCase
{
    /**
     * didChangeConfiguration does not need to do anything when no options/settings are passed
     */
    public function test_no_option_passed()
    {
        $client = new LanguageClient(new MockProtocolStream(), $writer = new MockProtocolStream());
        $projectIndex = new ProjectIndex($sourceIndex = new Index(), $dependenciesIndex = new DependenciesIndex());
        $definitionResolver = new DefinitionResolver($projectIndex);
        $loader = new PhpDocumentLoader(new FileSystemContentRetriever(), $projectIndex, $definitionResolver);
        $workspace = new Server\Workspace($client, $projectIndex, $dependenciesIndex, $sourceIndex, null, $loader, null);

        $result = $workspace->didChangeConfiguration();
        $this->assertFalse($result);
    }

    /**
     * When the passed options/settings do not differ from the previous, it has nothing to do
     */
    public function test_fails_with_invalid_options_type_or_format()
    {
        $options = new Options;
        $client = new LanguageClient(new MockProtocolStream(), $writer = new MockProtocolStream());
        $projectIndex = new ProjectIndex($sourceIndex = new Index(), $dependenciesIndex = new DependenciesIndex());
        $definitionResolver = new DefinitionResolver($projectIndex);
        $loader = new PhpDocumentLoader(new FileSystemContentRetriever(), $projectIndex, $definitionResolver);
        $workspace = new Server\Workspace($client, $projectIndex, $dependenciesIndex, $sourceIndex, null, $loader, null, null, null, null, $options);

        $this->expectException(\Exception::class);
        $this->workspace->didChangeConfiguration(['invalid' => 'options format']);
    }

    /**
     * When the passed options/settings do not differ from the previous, it has nothing to do
     */
    public function test_no_changed_options()
    {
        $options = new Options;
        $client = new LanguageClient(new MockProtocolStream(), $writer = new MockProtocolStream());
        $projectIndex = new ProjectIndex($sourceIndex = new Index(), $dependenciesIndex = new DependenciesIndex());
        $definitionResolver = new DefinitionResolver($projectIndex);
        $loader = new PhpDocumentLoader(new FileSystemContentRetriever(), $projectIndex, $definitionResolver);
        $workspace = new Server\Workspace($client, $projectIndex, $dependenciesIndex, $sourceIndex, null, $loader, null, null, null, null, $options);

        $result = $this->workspace->didChangeConfiguration($options);
        $this->assertFalse($result);
    }

    /**
     * Verify that the required methods for a reindex are called
     */
    public function test_fileTypes_option_triggers_a_reindex()
    {
        $sourceIndex = new Index;
        $dependenciesIndex = new DependenciesIndex;
        $projectIndex = $this->getMockBuilder('LanguageServer\Index\ProjectIndex')
                                ->setConstructorArgs([$sourceIndex, $dependenciesIndex])
                                ->setMethods(['wipe'])
                                ->getMock();
        $projectIndex->setComplete();

        $rootPath = realpath(__DIR__ . '/../../../fixtures/');
        $filesFinder = new FileSystemFilesFinder;
        $cache = new FileSystemCache;

        $definitionResolver = new DefinitionResolver($projectIndex);
        $client = new LanguageClient(new MockProtocolStream, new MockProtocolStream);
        $documentLoader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $textDocument = new Server\TextDocument($documentLoader, $definitionResolver, $client, $projectIndex);
        $indexer = $this->getMockBuilder('LanguageServer\Indexer')
                        ->setConstructorArgs([$filesFinder, $rootPath, $client, $cache, $dependenciesIndex, $sourceIndex, $documentLoader, null, null, new Options])
                        ->setMethods(['index'])
                        ->getMock();
        $workspace = new Server\Workspace($client, $projectIndex, $dependenciesIndex, $sourceIndex, null, $documentLoader, null, $indexer, new Options);


        $options = new Options;
        $options->fileTypes = [
            '.inc'
        ];

        $projectIndex->expects($this->once())->method('wipe');
        $indexer->expects($this->once())->method('index');

        // invoke event
        $result = $workspace->didChangeConfiguration($options);
        $this->assertTrue($result);
    }

    /**
     * Be sure that the indexer gets the new options/settings and uses them
     */
    public function test_indexer_uses_new_options()
    {
        $promise = new Promise;
        $sourceIndex = new Index;
        $dependenciesIndex = new DependenciesIndex;
        $projectIndex = new ProjectIndex($sourceIndex, $dependenciesIndex);
        $projectIndex->setComplete();

        $rootPath = realpath(__DIR__ . '/../../../fixtures/');
        $filesFinder = new FileSystemFilesFinder;
        $cache = new FileSystemCache;
        $initialOptions = new Options;

        $input = new MockProtocolStream;
        $output = new MockProtocolStream;

        $definitionResolver = new DefinitionResolver($projectIndex);
        $client = new LanguageClient($input, $output);
        $documentLoader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $textDocument = new Server\TextDocument($documentLoader, $definitionResolver, $client, $projectIndex);
        $indexer = new Indexer($filesFinder, $rootPath, $client, $cache, $dependenciesIndex, $sourceIndex, $documentLoader, null, null, $initialOptions);
        $workspace = new Server\Workspace($client, $projectIndex, $dependenciesIndex, $sourceIndex, null, $documentLoader, null, $indexer, $initialOptions);

        $output->on('message', function (Message $msg) use ($promise) {
            if ($msg->body->method === 'window/logMessage' && $promise->state === Promise::PENDING) {
                if ($msg->body->params->type === MessageType::ERROR) {
                    $promise->reject(new Exception($msg->body->params->message));
                } elseif (strpos($msg->body->params->message, 'All 1 PHP files parsed') !== false) {
                    $promise->fulfill();
                }
            }
        });

        $options = new Options;
        $options->fileTypes = [
            '.inc'
        ];

        $result = $workspace->didChangeConfiguration($options);
        $this->assertTrue($result);
        $promise->wait();
    }
}
