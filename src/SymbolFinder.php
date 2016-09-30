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
     * @var LanguageServer\Protocol\SymbolInformation[]
     */
    public $symbols = [];

    /**
     * @var string
     */
    private $uri;

    /**
     * @var string
     */
    private $containerName;

    /**
     * @var array
     */
    private $nameStack = [];

    /**
     * @var array
     */
    private $nodeStack = [];

    /**
     * @var int
     */
    private $functionCount = 0;

    public function __construct(string $uri)
    {
        $this->uri = $uri;
    }

    public function enterNode(Node $node)
    {
        $this->nodeStack[] = $node;
        $containerName = end($this->nameStack);

        // If we enter a named node, push its name onto name stack.
        // Else push the current name onto stack.
        if (!empty($node->name) && (is_string($node->name) || method_exists($node->name, '__toString')) && !empty((string)$node->name)) {
            if (empty($containerName)) {
                $this->nameStack[] = (string)$node->name;
            } else if ($node instanceof Node\Stmt\ClassMethod) {
                $this->nameStack[] = $containerName . '::' . (string)$node->name;
            } else {
                $this->nameStack[] = $containerName . '\\' . (string)$node->name;
            }
        } else {
            $this->nameStack[] = $containerName;
            // We are not interested in unnamed nodes, return
            return;
        }

        $class = get_class($node);
        if (!isset(self::NODE_SYMBOL_KIND_MAP[$class])) {
            return;
        }

        // if we enter a method or function, increase the function counter
        if ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
            $this->functionCount++;
        }

        $kind = self::NODE_SYMBOL_KIND_MAP[$class];

        // exclude non-global variable symbols.
        if ($kind === SymbolKind::VARIABLE && $this->functionCount > 0) {
            return;
        }

        $symbol = new SymbolInformation();
        $symbol->kind = $kind;
        $symbol->name = (string)$node->name;
        $symbol->location = new Location(
            $this->uri,
            new Range(
                new Position($node->getAttribute('startLine') - 1, $node->getAttribute('startColumn') - 1),
                new Position($node->getAttribute('endLine') - 1, $node->getAttribute('endColumn'))
            )
        );
        $symbol->containerName = $containerName;
        $this->symbols[] = $symbol;
    }

    public function leaveNode(Node $node)
    {
        array_pop($this->nodeStack);
        array_pop($this->nameStack);

        // if we leave a method or function, decrease the function counter
        if ($node instanceof Node\Stmt\Function_ || $node instanceof Node\Stmt\ClassMethod) {
            $this->functionCount--;
        }
    }
}
