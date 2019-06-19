<?php
declare(strict_types=1);

namespace LanguageServer\Tests\Server\TextDocument\Definition;

use Amp\Loop;
use LanguageServerProtocol\{TextDocumentIdentifier, Location};
use function LanguageServer\pathToUri;

class NamespacedTest extends GlobalTest
{
    public function getReferenceLocations(string $fqn): array
    {
        return parent::getReferenceLocations('TestNamespace\\' . $fqn);
    }

    public function getDefinitionLocation(string $fqn): Location
    {
        return parent::getDefinitionLocation('TestNamespace\\' . $fqn);
    }

    public function testDefinitionForConstants()
    {
        Loop::run(function () {
            // echo TEST_CONST;
            // Get definition for TEST_CONST
            $reference = $this->getReferenceLocations('TEST_CONST')[0];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TEST_CONST'), $result);
        });
    }

    public function testDefinitionForClassLikeUseStatement()
    {
        Loop::run(function () {
            // use TestNamespace\TestClass;
            // Get definition for TestClass
            $reference = $this->getReferenceLocations('TestClass')[7];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }

    public function testDefinitionForClassLikeGroupUseStatement()
    {
        Loop::run(function () {
            // use TestNamespace\{TestTrait, TestInterface};
            // Get definition for TestInterface
            $reference = $this->getReferenceLocations('TestClass')[1];
            $result = yield $this->textDocument->definition(
                new TextDocumentIdentifier($reference->uri),
                $reference->range->start
            );
            $this->assertEquals($this->getDefinitionLocation('TestClass'), $result);
        });
    }
}
