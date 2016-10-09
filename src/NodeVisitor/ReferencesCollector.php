<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node};

/**
 * Collects references to classes, interfaces, traits, methods, properties and constants
 * Depends on ReferencesAdder and NameResolver
 */
class ReferencesCollector extends NodeVisitorAbstract
{
    /**
     * Map from fully qualified name (FQN) to array of nodes that reference the symbol
     *
     * @var Node[][]
     */
    public $references;

    /**
     * @var Node[]
     */
    private $definitions;

    /**
     * @param Node[] $definitions The definitions that references should be tracked for
     */
    public function __construct(array $definitions)
    {
        $this->definitions = $definitions;
        $this->references = array_fill_keys(array_keys($definitions), []);
    }

    public function enterNode(Node $node)
    {
        // Check if the node references any global symbol
        $fqn = $node->getAttribute('ownerDocument')->getReferencedFqn($node);
        if ($fqn) {
            $this->references[$fqn][] = $node;
            // Static method calls, constant and property fetches also need to register a reference to the class
            // A reference like TestNamespace\TestClass::myStaticMethod() registers a reference for
            //  - TestNamespace\TestClass
            //  - TestNamespace\TestClass::myStaticMethod()
            if (
                ($node instanceof Node\Expr\StaticCall
                || $node instanceof Node\Expr\StaticPropertyFetch
                || $node instanceof Node\Expr\ClassConstFetch)
                && $node->class instanceof Node\Name
            ) {
                $this->references[(string)$node->class][] = $node->class;
            }
        }
    }
}
