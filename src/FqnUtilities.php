<?php

namespace LanguageServer;

use phpDocumentor\Reflection\{Type, Types};
use Microsoft\PhpParser;

class FqnUtilities
{
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
