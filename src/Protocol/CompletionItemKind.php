<?php

namespace LanguageServer\Protocol;

use PhpParser\Node;

/**
 * The kind of a completion entry.
 */
abstract class CompletionItemKind
{
    const TEXT = 1;
    const METHOD = 2;
    const FUNCTION = 3;
    const CONSTRUCTOR = 4;
    const FIELD = 5;
    const VARIABLE = 6;
    const _CLASS = 7;
    const INTERFACE = 8;
    const MODULE = 9;
    const PROPERTY = 10;
    const UNIT = 11;
    const VALUE = 12;
    const ENUM = 13;
    const KEYWORD = 14;
    const SNIPPET = 15;
    const COLOR = 16;
    const FILE = 17;
    const REFERENCE = 18;

    public static function fromSymbol(int $symbolKind)
    {
        $symbolCompletionKindMap = [
            SymbolKind::CLASS_ => CompletionItemKind::_CLASS,
            SymbolKind::INTERFACE => CompletionItemKind::INTERFACE,
            SymbolKind::FUNCTION => CompletionItemKind::FUNCTION,
            SymbolKind::METHOD => CompletionItemKind::METHOD,
            SymbolKind::FIELD => CompletionItemKind::FIELD,
            SymbolKind::CONSTRUCTOR => CompletionItemKind::CONSTRUCTOR,
            SymbolKind::VARIABLE => CompletionItemKind::VARIABLE,
        ];

        return $symbolCompletionKindMap[$symbolKind];
    }

    public static function fromNode(Node $node)
    {
        $nodeCompletionKindMap = [
            Node\Stmt\Class_::class           => CompletionItemKind::_CLASS,
            Node\Stmt\Trait_::class           => CompletionItemKind::_CLASS,
            Node\Stmt\Interface_::class       => CompletionItemKind::INTERFACE,

            Node\Stmt\Function_::class        => CompletionItemKind::FUNCTION,
            Node\Stmt\ClassMethod::class      => CompletionItemKind::METHOD,
            Node\Stmt\PropertyProperty::class => CompletionItemKind::PROPERTY,
            Node\Const_::class                => CompletionItemKind::FIELD
        ];
        $class = get_class($node);
        if (!isset($nodeCompletionKindMap[$class])) {
            throw new Exception("Not a declaration node: $class");
        }

        return $nodeCompletionKindMap[$class];
    }

}
