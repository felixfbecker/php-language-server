<?php
declare(strict_types = 1);

namespace LanguageServer\Index;

class DependenciesIndex extends AbstractAggregateIndex
{
    /**
     * Map from package name to index
     *
     * @var Index[]
     */
    protected $indexes = [];

    /**
     * @return Index[]
     */
    protected function getIndexes(): array
    {
        return $this->indexes;
    }

    /**
     * @param string $packageName
     * @return Index
     */
    public function getDependencyIndex(string $packageName): Index
    {
        if (!isset($this->indexes[$packageName])) {
            $this->indexes[$packageName] = new Index;
        }
        return $this->indexes[$packageName];
    }

    /**
     * @param string $packageName
     * @return void
     */
    public function removeDependencyIndex(string $packageName)
    {
        unset($this->indexes[$packageName]);
    }

    /**
     * @param string $packageName
     * @return bool
     */
    public function hasDependencyIndex(string $packageName): bool
    {
        return isset($this->indexes[$packageName]);
    }
}
