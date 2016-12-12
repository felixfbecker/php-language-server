<?php
declare(strict_types = 1);

namespace LanguageServer\Index;

use LanguageServer\Protocol\{SymbolInformation, TextDocumentIdentifier, ClientCapabilities};
use phpDocumentor\Reflection\DocBlockFactory;
use LanguageServer\ContentRetriever\ContentRetriever;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;

/**
 * A project index manages the source and dependency indexes
 */
class ProjectIndex extends AbstractAggregateIndex
{
    /**
     * The index for dependencies
     *
     * @var DependenciesIndex
     */
    private $dependenciesIndex;

    /**
     * The Index for the project source
     *
     * @var Index
     */
    private $sourceIndex;

    public function __construct(Index $sourceIndex, DependenciesIndex $dependenciesIndex)
    {
        $this->sourceIndex = $sourceIndex;
        $this->dependenciesIndex = $dependenciesIndex;
    }

    /**
     * @return ReadableIndex[]
     */
    protected function getIndexes(): array
    {
        return [$this->sourceIndex, $this->dependenciesIndex];
    }

    /**
     * @param string $uri
     * @return Index
     */
    public function getIndexForUri(string $uri): Index
    {
        if (preg_match('/\/vendor\/(\w+\/\w+)\//', $uri, $matches)) {
            $packageName = $matches[0];
            return $this->dependenciesIndex->getDependencyIndex($packageName);
        }
        return $this->sourceIndex;
    }
}
