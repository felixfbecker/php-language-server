<?php
declare(strict_types = 1);

namespace LanguageServer\Index;

/**
 * The ReadableIndex interface provides methods to lookup definitions and references
 */
interface ReadableIndex
{
    /**
     * Returns an associative array [string => Definition] that maps fully qualified symbol names
     * to Definitions
     *
     * @return Definitions[]
     */
    public function getDefinitions(): array;

    /**
     * Returns the Definition object by a specific FQN
     *
     * @param string $fqn
     * @param bool $globalFallback Whether to fallback to global if the namespaced FQN was not found
     * @return Definition|null
     */
    public function getDefinition(string $fqn, bool $globalFallback = false);

    /**
     * Returns all URIs in this index that reference a symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return string[]
     */
    public function getReferenceUris(string $fqn): array;
}
