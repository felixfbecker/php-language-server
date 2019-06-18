<?php
declare(strict_types=1);

namespace LanguageServer\Tests\Server\TextDocument\References;

use Amp\Loop;
use LanguageServerProtocol\{TextDocumentIdentifier, Position, ReferenceContext, Location, Range};
use function LanguageServer\pathToUri;

class NamespacedTest extends GlobalTest
{
    protected function getReferenceLocations(string $fqn): array
    {
        return parent::getReferenceLocations('TestNamespace\\' . $fqn);
    }

    protected function getDefinitionLocation(string $fqn): Location
    {
        return parent::getDefinitionLocation('TestNamespace\\' . $fqn);
    }

    public function testReferencesForNamespaces()
    {
        Loop::run(function () {
            // namespace TestNamespace;
            // Get references for TestNamespace
            $definition = parent::getDefinitionLocation('TestNamespace');
            $result = yield $this->textDocument->references(
                new ReferenceContext,
                new TextDocumentIdentifier($definition->uri),
                $definition->range->end
            );
            $this->assertEquals(parent::getReferenceLocations('TestNamespace'), $result);
        });
    }
}
