<?php

namespace LanguageServer\Protocol;

use PhpParser\Node;
use Microsoft\PhpParser as Tolerant;
use Exception;

/**
 * Represents information about programming constructs like variables, classes,
 * interfaces etc.
 */
class TolerantSymbolInformation extends SymbolInformation
{
    /**
     * Converts a Node to a SymbolInformation
     *
     * @param Tolerant\Node $node
     * @param string $fqn If given, $containerName will be extracted from it
     * @return self|null
     */
    public static function fromNode($node, string $fqn = null)
    {
        $parent = $node->getAttribute('parentNode');
        $symbol = new self;
        if ($node instanceof Node\Stmt\Class_) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if ($node instanceof Node\Stmt\Trait_) {
            $symbol->kind = SymbolKind::CLASS_;
        } else if ($node instanceof Node\Stmt\Interface_) {
            $symbol->kind = SymbolKind::INTERFACE;
        } else if ($node instanceof Node\Name && $parent instanceof Node\Stmt\Namespace_) {
            $symbol->kind = SymbolKind::NAMESPACE;
        } else if ($node instanceof Node\Stmt\Function_) {
            $symbol->kind = SymbolKind::FUNCTION;
        } else if ($node instanceof Node\Stmt\ClassMethod) {
            $symbol->kind = SymbolKind::METHOD;
        } else if ($node instanceof Node\Stmt\PropertyProperty) {
            $symbol->kind = SymbolKind::PROPERTY;
        } else if ($node instanceof Node\Const_) {
            $symbol->kind = SymbolKind::CONSTANT;
        } else if (
            (
                ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp)
                && $node->var instanceof Node\Expr\Variable
            )
            || $node instanceof Node\Expr\ClosureUse
            || $node instanceof Node\Param
        ) {
            $symbol->kind = SymbolKind::VARIABLE;
        } else {
            return null;
        }
        if ($node instanceof Node\Name) {
            $symbol->name = (string)$node;
        } else if ($node instanceof Node\Expr\Assign || $node instanceof Node\Expr\AssignOp) {
            $symbol->name = $node->var->name;
        } else if ($node instanceof Node\Expr\ClosureUse) {
            $symbol->name = $node->var;
        } else if (isset($node->name)) {
            $symbol->name = (string)$node->name;
        } else {
            return null;
        }
        $symbol->location = Location::fromNode($node);
        if ($fqn !== null) {
            $parts = preg_split('/(::|->|\\\\)/', $fqn);
            array_pop($parts);
            $symbol->containerName = implode('\\', $parts);
        }
        return $symbol;
    }
}
