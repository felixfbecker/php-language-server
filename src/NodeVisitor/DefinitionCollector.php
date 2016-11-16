<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\Definition;
use function LanguageServer\Fqn\getDefinedFqn;

/**
 * Collects definitions of classes, interfaces, traits, methods, properties and constants
 * Depends on ReferencesAdder and NameResolver
 */
class DefinitionCollector extends NodeVisitorAbstract
{
    /**
     * Map from fully qualified name (FQN) to Definition
     *
     * @var Definition[]
     */
    public $definitions = [];

    /**
     * Map from fully qualified name (FQN) to Node
     *
     * @var Node[]
     */
    public $nodes = [];

    public function enterNode(Node $node)
    {
        $fqn = getDefinedFqn($node);
        if ($fqn === null) {
            return;
        }
        $this->nodes[$fqn] = $node;
        $this->definitions[$fqn] = Definition::fromNode($node);
    }
}
