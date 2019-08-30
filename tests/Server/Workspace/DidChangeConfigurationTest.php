<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\Workspace;

use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\{DefinitionResolver, LanguageClient, PhpDocumentLoader, Server};
use LanguageServer\Index\{DependenciesIndex, Index, ProjectIndex};
use LanguageServer\Message;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use LanguageServer\Server\Workspace;
use Sabre\Event\Loop;
use LanguageServer\Configuration;

class DidChangeConfigurationTest extends ServerTestCase
{
    public function testChangeConfiguration()
    {
        $client = new LanguageClient(new MockProtocolStream(), $writer = new MockProtocolStream());
        $projectIndex = new ProjectIndex($sourceIndex = new Index(), $dependenciesIndex = new DependenciesIndex());
        $definitionResolver = new DefinitionResolver($projectIndex);
        $loader = new PhpDocumentLoader(new FileSystemContentRetriever(), $projectIndex, $definitionResolver);
        $workspace = new Server\Workspace($client, $projectIndex, $dependenciesIndex, $sourceIndex, null, $loader, null, new Configuration([ 'foo' ]));

        $this->assertInstanceOf(Configuration::class, $workspace->configuration);

        $changesToConfig = [ 'excludePatterns' => ['foo', 'bar'] ];

        $writer->on('message', function (Message $message) use ($changesToConfig) {
            if ($message->body->method === 'workspace/didChangeConfiguration') {
                $this->assertEquals($message->body->params->settings, $changesToConfig);
            }
        });

        $workspace->didChangeConfiguration([ 'settings' => $changesToConfig  ]);
        Loop\tick(true);

        $this->assertEquals($workspace->configuration->excludePatterns, $changesToConfig['excludePatterns']);
    }
}
