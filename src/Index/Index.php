<?php
declare(strict_types = 1);

namespace LanguageServer\Index;

use LanguageServer\Definition;
use Sabre\Event\EmitterTrait;

/**
 * Represents the index of a project or dependency
 * Serializable for caching
 */
class Index implements ReadableIndex, \Serializable
{
    use EmitterTrait;

    /**
     * An associative array that maps splitted fully qualified symbol names
     * to member definitions, eg :
     * [
     *     'Psr' => [
     *         '\Log' => [
     *             '\LoggerInterface' => [
     *                 '->log()' => $definition,
     *             ],
     *         ],
     *     ],
     * ]
     *
     * @var array
     */
    private $memberDefinitions = [];

    /**
     * An associative array that maps splitted fully qualified symbol names
     * to non member definitions, eg :
     * [
     *     'Psr' => [
     *         '\Log' => [
     *             '\LoggerInterface' => $definition,
     *         ],
     *     ],
     * ]
     *
     * @var array
     */
    private $nonMemberDefinitions = [];


    /**
     * An associative array that maps fully qualified symbol names
     * to arrays of document URIs that reference the symbol
     *
     * @var string[][]
     */
    private $references = [];

    /**
     * @var bool
     */
    private $complete = false;

    /**
     * @var bool
     */
    private $staticComplete = false;

    /**
     * Marks this index as complete
     *
     * @return void
     */
    public function setComplete()
    {
        if (!$this->isStaticComplete()) {
            $this->setStaticComplete();
        }
        $this->complete = true;
        $this->emit('complete');
    }

    /**
     * Marks this index as complete for static definitions and references
     *
     * @return void
     */
    public function setStaticComplete()
    {
        $this->staticComplete = true;
        $this->emit('static-complete');
    }

    /**
     * Returns true if this index is complete
     *
     * @return bool
     */
    public function isComplete(): bool
    {
        return $this->complete;
    }

    /**
     * Returns true if this index is complete
     *
     * @return bool
     */
    public function isStaticComplete(): bool
    {
        return $this->staticComplete;
    }

    /**
     * Returns a Generator providing an associative array [string => Definition]
     * that maps fully qualified symbol names to Definitions (global or not)
     *
     * @return \Generator providing Definition[]
     */
    public function getDefinitions(): \Generator
    {
        // foreach ($this->fqnDefinitions as $fqnDefinition) {
        //     foreach ($fqnDefinition as $fqn => $definition) {
        //         yield $fqn => $definition;
        //     }
        // }

        yield from $this->yieldDefinitionsRecursively($this->memberDefinitions);
        yield from $this->yieldDefinitionsRecursively($this->nonMemberDefinitions);
    }

    /**
     * Returns a Generator providing the Definitions that are in the given FQN
     *
     * @param string $fqn
     * @return \Generator providing Definitions[]
     */
    public function getDefinitionsForFqn(string $fqn): \Generator
    {
        // foreach ($this->fqnDefinitions[$fqn] ?? [] as $symbolFqn => $definition) {
        //     yield $symbolFqn => $definition;
        // }

        $parts = $this->splitFqn($fqn);
        $result = $this->getIndexValue($parts, $this->memberDefinitions);

        if ($result instanceof Definition) {
            yield $fqn => $result;
        } elseif (is_array($result)) {
            yield from $this->yieldDefinitionsRecursively($result, $fqn);
        } else {
            $result = $this->getIndexValue($parts, $this->nonMemberDefinitions);

            if ($result instanceof Definition) {
                yield $fqn => $result;
            } elseif (is_array($result)) {
                yield from $this->yieldDefinitionsRecursively($result, $fqn);
            }
        }
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
        // $namespacedFqn = $this->extractNamespacedFqn($fqn);
        // $definitions = $this->fqnDefinitions[$namespacedFqn] ?? [];

        // if (isset($definitions[$fqn])) {
        //     return $definitions[$fqn];
        // }

        // if ($globalFallback) {
        //     $parts = explode('\\', $fqn);
        //     $fqn = end($parts);
        //     return $this->getDefinition($fqn);
        // }

        $parts = $this->splitFqn($fqn);
        $result = $this->getIndexValue($parts, $this->memberDefinitions);

        if ($result instanceof Definition) {
            return $result;
        }

        $result = $this->getIndexValue($parts, $this->nonMemberDefinitions);

        return $result instanceof Definition
            ? $result
            : null
        ;
    }

    /**
     * Registers a definition
     *
     * @param string $fqn The fully qualified name of the symbol
     * @param Definition $definition The Definition object
     * @return void
     */
    public function setDefinition(string $fqn, Definition $definition)
    {
        // $namespacedFqn = $this->extractNamespacedFqn($fqn);
        // if (!isset($this->fqnDefinitions[$namespacedFqn])) {
        //     $this->fqnDefinitions[$namespacedFqn] = [];
        // }

        // $this->fqnDefinitions[$namespacedFqn][$fqn] = $definition;

        $parts = $this->splitFqn($fqn);

        if ($definition->isMember) {
            $this->indexDefinition(0, $parts, $this->memberDefinitions, $definition);
        } else {
            $this->indexDefinition(0, $parts, $this->nonMemberDefinitions, $definition);
        }

        $this->emit('definition-added');
    }

    /**
     * Unsets the Definition for a specific symbol
     * and removes all references pointing to that symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return void
     */
    public function removeDefinition(string $fqn)
    {
        // $namespacedFqn = $this->extractNamespacedFqn($fqn);
        // if (isset($this->fqnDefinitions[$namespacedFqn])) {
        //     unset($this->fqnDefinitions[$namespacedFqn][$fqn]);

        //     if (empty($this->fqnDefinitions[$namespacedFqn])) {
        //         unset($this->fqnDefinitions[$namespacedFqn]);
        //     }
        // }

        $parts = $this->splitFqn($fqn);

        if (true !== $this->removeIndexedDefinition(0, $parts, $this->memberDefinitions)) {
            $this->removeIndexedDefinition(0, $parts, $this->nonMemberDefinitions);
        }

        unset($this->references[$fqn]);
    }

    /**
     * Returns a Generator providing all URIs in this index that reference a symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return \Generator providing string[]
     */
    public function getReferenceUris(string $fqn): \Generator
    {
        foreach ($this->references[$fqn] ?? [] as $uri) {
            yield $uri;
        }
    }

    /**
     * For test use.
     * Returns all references, keyed by fqn.
     *
     * @return string[][]
     */
    public function getReferences(): array
    {
        return $this->references;
    }

    /**
     * Adds a document URI as a referencee of a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @return void
     */
    public function addReferenceUri(string $fqn, string $uri)
    {
        if (!isset($this->references[$fqn])) {
            $this->references[$fqn] = [];
        }
        // TODO: use DS\Set instead of searching array
        if (array_search($uri, $this->references[$fqn], true) === false) {
            $this->references[$fqn][] = $uri;
        }
    }

    /**
     * Removes a document URI as the container for a specific symbol
     *
     * @param string $fqn The fully qualified name of the symbol
     * @param string $uri The URI
     * @return void
     */
    public function removeReferenceUri(string $fqn, string $uri)
    {
        if (!isset($this->references[$fqn])) {
            return;
        }
        $index = array_search($fqn, $this->references[$fqn], true);
        if ($index === false) {
            return;
        }
        array_splice($this->references[$fqn], $index, 1);
    }

    /**
     * @param string $serialized
     * @return void
     */
    public function unserialize($serialized)
    {
        $data = unserialize($serialized);

        foreach ($data as $prop => $val) {
            $this->$prop = $val;
        }
    }

    /**
     * @param string $serialized
     * @return string
     */
    public function serialize()
    {
        return serialize([
            'memberDefinitions' => $this->memberDefinitions,
            'nonMemberDefinitions' => $this->nonMemberDefinitions,
            'references' => $this->references,
            'complete' => $this->complete,
            'staticComplete' => $this->staticComplete
        ]);
    }

    /**
     * @param string $fqn The symbol FQN
     * @return string The namespaced FQN extracted from the given symbol FQN
     */
    // private function extractNamespacedFqn(string $fqn): string
    // {
    //     foreach (['::', '->'] as $operator) {
    //         if (false !== ($pos = strpos($fqn, $operator))) {
    //             return substr($fqn, 0, $pos);
    //         }
    //     }

    //     return $fqn;
    // }

    /**
     * Returns a Genrerator containing all the into the given $storage recursively.
     * The generator yields key => value pairs, eg
     * 'Psr\Log\LoggerInterface->log()' => $definition
     *
     * @param array &$storage
     * @param string $prefix (optional)
     * @return \Generator
     */
    private function yieldDefinitionsRecursively(array &$storage, string $prefix = ''): \Generator
    {
        foreach ($storage as $key => $value) {
            if (!is_array($value)) {
                yield sprintf('%s%s', $prefix, $key) => $value;
            } else {
                yield from $this->yieldDefinitionsRecursively($value, sprintf('%s%s', $prefix, $key));
            }
        }
    }

    /**
     * Splits the given FQN into an array, eg :
     * 'Psr\Log\LoggerInterface->log' will be ['Psr', '\Log', '\LoggerInterface', '->log()']
     * '\Exception->getMessage()'     will be ['\Exception', '->getMessage()']
     * 'PHP_VERSION'                  will be ['PHP_VERSION']
     *
     * @param string $fqn
     * @return array
     */
    private function splitFqn(string $fqn): array
    {
        // split fqn at backslashes
        $parts = explode('\\', $fqn);

        // write back the backslach prefix to the first part if it was present
        if ('' === $parts[0]) {
            if (count($parts) > 1) {
                $parts = array_slice($parts, 1);
            }

            $parts[0] = sprintf('\\%s', $parts[0]);
        }

        // write back the backslashes prefixes for the other parts
        for ($i = 1; $i < count($parts); $i++) {
            $parts[$i] = sprintf('\\%s', $parts[$i]);
        }

        // split the last part in 2 parts at the operator
        $lastPart = end($parts);
        foreach (['::', '->'] as $operator) {
            $endParts = explode($operator, $lastPart);
            if (count($endParts) > 1) {
                // replace the last part by its pieces
                array_pop($parts);
                $parts[] = $endParts[0];
                $parts[] = sprintf('%s%s', $operator, $endParts[1]);
                break;
            }
        }

        return $parts;
    }

    /**
     * Return the values stored in this index under the given $parts array.
     * It can be an index node or a Definition if the $parts are precise
     * enough. Returns null when nothing is found.
     *
     * @param array $parts            The splitted FQN
     * @param array &$storage         The array in which to store the $definition
     * @return array|Definition|null
     */
    private function getIndexValue(array $parts, array &$storage)
    {
        $part = $parts[0];

        if (!isset($storage[$part])) {
            return null;
        }

        $parts = array_slice($parts, 1);
        // we've reached the last provided part
        if (0 === count($parts)) {
            return $storage[$part];
        }

        if (!is_array($storage[$part]) && count($parts) > 0) {
            // we're looking for a member definition in the non member index,
            // no matches can be found.
            return null;
        }

        return $this->getIndexValue($parts, $storage[$part]);
    }

    /**
     * Recusrive function which store the given definition in the given $storage
     * array represented as a tree matching the given $parts.
     *
     * @param int $level              The current level of FQN part
     * @param array $parts            The splitted FQN
     * @param array &$storage         The array in which to store the $definition
     * @param Definition $definition  The Definition to store
     */
    private function indexDefinition(int $level, array $parts, array &$storage, Definition $definition)
    {
        $part = $parts[$level];

        if ($level + 1 === count($parts)) {
            $storage[$part] = $definition;

            return;
        }

        if (!isset($storage[$part])) {
            $storage[$part] = [];
        }

        if (!is_array($storage[$part])) {
            // it's a non member definition, we can't add it to the member
            // definitions index
            return;
        }

        $this->indexDefinition($level + 1, $parts, $storage[$part], $definition);
    }

    /**
     * Recusrive function which remove the definition matching the given $parts
     * from the given $storage array.
     * The function also looks up recursively to remove the parents of the
     * definition which no longer has children to avoid to let empty arrays
     * in the index.
     *
     * @param int $level              The current level of FQN part
     * @param array $parts            The splitted FQN
     * @param array &$storage         The current array in which to remove data
     * @param array &$rootStorage     The root storage array
     * @return boolean|null           True when the definition has been found and removed, null otherwise.
     */
    private function removeIndexedDefinition(int $level, array $parts, array &$storage, &$rootStorage)
    {
        $part = $parts[$level];

        if ($level + 1 === count($parts)) {
            if (isset($storage[$part]) && count($storage[$part]) < 2) {
                unset($storage[$part]);

                if (0 === $level) {
                    // we're at root level, no need to check for parents
                    // w/o children
                    return true;
                }

                array_pop($parts);
                // parse again the definition tree to see if the parent
                // can be removed too if it has no more children
                return $this->removeIndexedDefinition(0, $parts, $rootStorage, $rootStorage);
            }
        } else {
            return $this->removeIndexedDefinition($level + 1, $parts, $storage[$part], $rootStorage);
        }
    }
}
