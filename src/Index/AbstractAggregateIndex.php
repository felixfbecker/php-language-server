<?php
declare(strict_types = 1);

namespace LanguageServer\Index;

use LanguageServer\Definition;
use Sabre\Event\EmitterTrait;

abstract class AbstractAggregateIndex implements ReadableIndex
{
    use EmitterTrait;

    /**
     * Returns all indexes managed by the aggregate index
     *
     * @return ReadableIndex[]
     */
    abstract protected function getIndexes(): array;

    public function __construct()
    {
        foreach ($this->getIndexes() as $index) {
            $this->registerIndex($index);
        }
    }

    /**
     * @param ReadableIndex $index
     */
    protected function registerIndex(ReadableIndex $index)
    {
        $index->on('complete', function () {
            if ($this->isComplete()) {
                $this->emit('complete');
            }
        });
        $index->on('static-complete', function () {
            if ($this->isStaticComplete()) {
                $this->emit('static-complete');
            }
        });
        $index->on('definition-added', function () {
            $this->emit('definition-added');
        });
    }

    /**
     * Marks this index as complete
     *
     * @return void
     */
    public function setComplete()
    {
        foreach ($this->getIndexes() as $index) {
            $index->setComplete();
        }
    }

    /**
     * Marks this index as complete for static definitions and references
     *
     * @return void
     */
    public function setStaticComplete()
    {
        foreach ($this->getIndexes() as $index) {
            $index->setStaticComplete();
        }
    }

    /**
     * Returns true if this index is complete
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        foreach ($this->getIndexes() as $index) {
            if (!$index->isComplete()) {
                return false;
            }
        }
        return true;
    }

    /**
     * Returns true if this index is complete for static definitions or references
     *
     * @return bool
     */
    public function isStaticComplete(): bool
    {
        foreach ($this->getIndexes() as $index) {
            if (!$index->isStaticComplete()) {
                return false;
            }
        }
        return true;
    }

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
