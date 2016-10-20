<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use function LanguageServer\Fqn\getReferencedFqn;
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
        $fqn = getReferencedFqn($node);
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
