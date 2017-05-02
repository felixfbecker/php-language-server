<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\{
    Definition, FqnUtilities, TolerantDefinitionResolver
};

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

    private $definitionResolver;

    public function __construct(TolerantDefinitionResolver $definitionResolver)
    {
        $this->definitionResolver = $definitionResolver;
    }

    public function enterNode(Node $node)
    {
        $fqn = FqnUtilities::getDefinedFqn($node);
        // Only index definitions with an FQN (no variables)
        if ($fqn === null) {
            return;
        }
        $this->nodes[$fqn] = $node;
        $this->definitions[$fqn] = $this->definitionResolver->createDefinitionFromNode($node, $fqn);
    }
}
