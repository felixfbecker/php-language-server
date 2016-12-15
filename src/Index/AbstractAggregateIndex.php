<?php
declare(strict_types = 1);

namespace LanguageServer\Index;

use LanguageServer\Definition;

abstract class AbstractAggregateIndex implements ReadableIndex
{
    /**
     * Returns all indexes managed by the aggregate index
     *
     * @return ReadableIndex[]
     */
    abstract protected function getIndexes(): array;

    /**
     * Returns an associative array [string => Definition] that maps fully qualified symbol names
     * to Definitions
     *
     * @return Definition[]
     */
    public function getDefinitions(): array
    {
        $defs = [];
        foreach ($this->getIndexes() as $index) {
            foreach ($index->getDefinitions() as $fqn => $def) {
                $defs[$fqn] = $def;
            }
        }
        return $defs;
    }

    /**
     * Returns the Definition object by a specific FQN
     *
     * @param string $fqn
     * @param bool $globalFallback Whether to fallback to global if the namespaced FQN was not found
     * @return Definition|null
     */
    public function getDefinition(string $fqn, bool $globalFallback = false)
    {
        foreach ($this->getIndexes() as $index) {
            if ($def = $index->getDefinition($fqn, $globalFallback)) {
                return $def;
            }
        }
    }

    /**
     * Returns all URIs in this index that reference a symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return string[]
     */
    public function getReferenceUris(string $fqn): array
    {
        $refs = [];
        foreach ($this->getIndexes() as $index) {
            foreach ($index->getReferenceUris($fqn) as $ref) {
                $refs[] = $ref;
            }
        }
        return $refs;
    }
}
