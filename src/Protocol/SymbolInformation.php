<?php

namespace LanguageServer\Protocol;

use PhpParser\Node;
use Exception;

/**
 * Represents information about programming constructs like variables, classes,
 * interfaces etc.
 */
class SymbolInformation
{
    /**
     * The name of this symbol.
     *
     * @var string
     */
    public $name;

    /**
     * The kind of this symbol.
     *
     * @var number
     */
    public $kind;

    /**
     * The location of this symbol.
     *
     * @var Location
     */
    public $location;

    /**
     * The name of the symbol containing this symbol.
     *
     * @var string|null
     */
    public $containerName;

    /**
     * Converts a Node to a SymbolInformation
     *
     * @param Node $node
     * @param string $fqn If given, $containerName will be extracted from it
     * @return self
     */
    public static function fromNode(Node $node, string $fqn = null)
    {
        $nodeSymbolKindMap = [
            Node\Stmt\Class_::class           => SymbolKind::CLASS_,
            Node\Stmt\Trait_::class           => SymbolKind::CLASS_,
            Node\Stmt\Interface_::class       => SymbolKind::INTERFACE,
            Node\Stmt\Namespace_::class       => SymbolKind::NAMESPACE,
            Node\Stmt\Function_::class        => SymbolKind::FUNCTION,
            Node\Stmt\ClassMethod::class      => SymbolKind::METHOD,
            Node\Stmt\PropertyProperty::class => SymbolKind::FIELD,
            Node\Const_::class                => SymbolKind::CONSTANT
        ];
        $class = get_class($node);
        if (!isset($nodeSymbolKindMap[$class])) {
            throw new Exception("Not a declaration node: $class");
        }
        $symbol = new self;
        $symbol->kind = $nodeSymbolKindMap[$class];
        $symbol->name = (string)$node->name;
        $symbol->location = Location::fromNode($node);
        if ($fqn !== null) {
            $parts = preg_split('/(::|\\\\)/', $fqn);
            array_pop($parts);
            $symbol->containerName = implode('\\', $parts);
        }
        return $symbol;
    }

    /**
     * @param string $name
     * @param int $kind
     * @param Location $location
     * @param string $containerName
     */
    public function __construct($name = null, $kind = null, $location = null, $containerName = null)
    {
        $this->name = $name;
        $this->kind = $kind;
        $this->location = $location;
        $this->containerName = $containerName;
    }
}
