<?php
declare(strict_types = 1);

namespace LanguageServer\Tests\Server\TextDocument\References;

use LanguageServer\Protocol\Location;
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
}
