<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\{NodeVisitorAbstract, Node};
use LanguageServer\Protocol\{SymbolInformation, SymbolKind, Range, Position, Location};

class SymbolFinder extends NodeVisitorAbstract
{
    const NODE_SYMBOL_KIND_MAP = [
        Node\Stmt\Class_::class           => SymbolKind::CLASS_,
        Node\Stmt\Trait_::class           => SymbolKind::CLASS_,
        Node\Stmt\Interface_::class       => SymbolKind::INTERFACE,
        Node\Stmt\Namespace_::class       => SymbolKind::NAMESPACE,
        Node\Stmt\Function_::class        => SymbolKind::FUNCTION,
        Node\Stmt\ClassMethod::class      => SymbolKind::METHOD,
        Node\Stmt\PropertyProperty::class => SymbolKind::PROPERTY,
        Node\Const_::class                => SymbolKind::CONSTANT,
        Node\Expr\Variable::class         => SymbolKind::VARIABLE
    ];

    /**
     * @var \LanguageServer\Protocol\SymbolInformation[]
     */
    public $symbols;

    /**
     * @var string
     */
    private $uri;

    /**
     * @var string
     */
    private $containerName;

    public function __construct(string $uri)
    {
        $this->uri = $uri;
    }

    public function enterNode(Node $node)
    {
        $class = get_class($node);
        if (!isset(self::NODE_SYMBOL_KIND_MAP[$class])) {
        return;
    }

        $symbol = end($this->symbols);
        $kind = self::NODE_SYMBOL_KIND_MAP[$class];

        // exclude variable symbols that are defined in methods and functions.
        if ($symbol && $kind === SymbolKind::VARIABLE &&
            ($symbol->kind === SymbolKind::METHOD || $symbol->kind === SymbolKind::FUNCTION)
        ) {
            if (
                $node->getAttribute('startLine') - 1 > $symbol->location->range->start->line &&
                $node->getAttribute('endLine') - 1 < $symbol->location->range->end->line
            ) {
                return;
            }
        }

        $symbol = new SymbolInformation();
        $symbol->kind = self::NODE_SYMBOL_KIND_MAP[$class];
        $symbol->name = (string)$node->name;
        $symbol->location = new Location(
            $this->uri,
            new Range(
                new Position($node->getAttribute('startLine') - 1, $node->getAttribute('startColumn') - 1),
                new Position($node->getAttribute('endLine') - 1, $node->getAttribute('endColumn') - 1)
            )
        );
        $symbol->containerName = $this->containerName;
        $this->containerName = $symbol->name;
        $this->symbols[] = $symbol;
    }

    public function leaveNode(Node $node)
    {
        $this->containerName = null;
    }
}
