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
    public $references = [];

    public function enterNode(Node $node)
    {
        // Check if the node references any global symbol
        $fqn = $node->getAttribute('ownerDocument')->getReferencedFqn($node);
        if ($fqn) {
            $this->addReference($fqn, $node);
            // Namespaced constant access and function calls also need to register a reference
            // to the global version because PHP falls back to global at runtime
            // http://php.net/manual/en/language.namespaces.fallback.php
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Expr\ConstFetch || $parent instanceof Node\Expr\FuncCall) {
                $parts = explode('\\', $fqn);
                if (count($parts) > 1) {
                    $globalFqn = end($parts);
                    $this->addReference($globalFqn, $node);
                }
            }
            // Namespaced constant references and function calls also need to register a reference to the global
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
                $this->addReference((string)$node->class, $node->class);
            }
        }
    }

    private function addReference(string $fqn, Node $node)
    {
        if (!isset($this->references[$fqn])) {
            $this->references[$fqn] = [];
        }
        $this->references[$fqn][] = $node;
    }
}
