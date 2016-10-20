<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\Protocol\SymbolInformation;
use function LanguageServer\Fqn\getDefinedFqn;

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

    /**
     * Map from FQN to SymbolInformation
     *
     * @var SymbolInformation
     */
    public $symbols = [];

    public function enterNode(Node $node)
    {
        $fqn = getDefinedFqn($node);
        if ($fqn === null) {
            return;
        }
        $this->definitions[$fqn] = $node;
        $symbol = SymbolInformation::fromNode($node, $fqn);
        $this->symbols[$fqn] = $symbol;
    }
}
