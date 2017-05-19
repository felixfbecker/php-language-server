<?php

namespace LanguageServer;

use phpDocumentor\Reflection\{Type, Types};
use PhpParser\Node;
use Microsoft\PhpParser as Tolerant;

class FqnUtilities
{
    /**
     * Returns the fully qualified name (FQN) that is defined by a node
     * Returns null if the node does not declare any symbol that can be referenced by an FQN
     *
     * @param Node | Tolerant\Node $node
     * @return string|null
     */
    public static function getDefinedFqn($node)
    {
        if ($node instanceof Node) {
            return DefinitionResolver::getDefinedFqn($node);
        } elseif ($node instanceof Tolerant\Node) {
            return DefinitionResolver::getDefinedFqn($node);
        }

        throw new \TypeError("Unspported Node class");
    }

    /**
     * Returns all possible FQNs in a type
     *
     * @param Type|null $type
     * @return string[]
     */
    public static function getFqnsFromType($type): array
    {
        $fqns = [];
        if ($type instanceof Types\Object_) {
            $fqsen = $type->getFqsen();
            if ($fqsen !== null) {
                $fqns[] = substr((string)$fqsen, 1);
            }
        }
        if ($type instanceof Types\Compound) {
            for ($i = 0; $t = $type->get($i); $i++) {
                foreach (self::getFqnsFromType($type) as $fqn) {
                    $fqns[] = $fqn;
                }
            }
        }
        return $fqns;
    }
}