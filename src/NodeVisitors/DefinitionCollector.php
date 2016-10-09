<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitors;

use PhpParser\{NodeVisitorAbstract, Node};

/**
 * Collects definitions of classes, interfaces, traits, methods, properties and constants
 * Depends on ReferencesAdder and NameResolver
 */
class DefinitionCollector extends NodeVisitorAbstract
{
    /**
     * Map from fully qualified name (FQN) to Node
     *
     * @var Node[]
     */
    public $definitions = [];

    public function enterNode(Node $node)
    {
        $fqn = $node->getAttribute('ownerDocument')->getDefinedFqn($node);
        if ($fqn !== null) {
            $this->definitions[$fqn] = $node;
        }
    }
}
