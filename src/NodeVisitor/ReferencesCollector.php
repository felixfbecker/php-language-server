<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\DefinitionResolver;

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
    public $nodes = [];

    /**
     * @var DefinitionResolver
     */
    private $definitionResolver;

    /**
     * @param DefinitionResolver $definitionResolver The DefinitionResolver to resolve reference nodes to definitions
     */
    public function __construct(DefinitionResolver $definitionResolver)
    {
        $this->definitionResolver = $definitionResolver;
    }

    public function enterNode(Node $node)
    {
        // Check if the node references any global symbol
        $fqn = $this->definitionResolver->resolveReferenceNodeToFqn($node);
        if ($fqn) {
            $parent = $node->getAttribute('parentNode');
            $grandParent = $parent ? $parent->getAttribute('parentNode') : null;
            $this->addReference($fqn, $node);
            if (
                $node instanceof Node\Name
                && $node->isQualified()
                && !($parent instanceof Node\Stmt\Namespace_ && $parent->name === $node)
            ) {
                // Add references for each referenced namespace
                $ns = $fqn;
                while (($pos = strrpos($ns, '\\')) !== false) {
                    $ns = substr($ns, 0, $pos);
                    $this->addReference($ns, $node);
                }
            }
            // Namespaced constant access and function calls also need to register a reference
            // to the global version because PHP falls back to global at runtime
            // http://php.net/manual/en/language.namespaces.fallback.php
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
        if (!isset($this->nodes[$fqn])) {
            $this->nodes[$fqn] = [];
        }
        $this->nodes[$fqn][] = $node;
    }
}
