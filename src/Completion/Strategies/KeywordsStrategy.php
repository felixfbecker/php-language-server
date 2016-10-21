<?php
declare(strict_types = 1);

namespace LanguageServer\Completion\Strategies;

use LanguageServer\Protocol\ {
    Range,
    CompletionItemKind
};
use LanguageServer\Completion\ {
    ICompletionStrategy,
    CompletionContext,
    CompletionReporter
};

class KeywordsStrategy implements ICompletionStrategy
{

    /**
     * @var string[]
     */
    const KEYWORDS = [
        "abstract",
        "and",
        "array",
        "as",
        "break",
        "callable",
        "case",
        "catch",
        "class",
        "clone",
        "const",
        "continue",
        "declare",
        "default",
        "die",
        "do",
        "echo",
        "else",
        "elseif",
        "empty",
        "enddeclare",
        "endfor",
        "endforeach",
        "endif",
        "endswitch",
        "endwhile",
        "eval",
        "exit",
        "extends",
        "false",
        "final",
        "finally",
        "for",
        "foreach",
        "function",
        "global",
        "goto",
        "if",
        "implements",
        "include",
        "include_once",
        "instanceof",
        "insteadof",
        "interface",
        "isset",
        "list",
        "namespace",
        "new",
        "null",
        "or",
        "parent",
        "print",
        "private",
        "protected",
        "public",
        "require",
        "require_once",
        "return",
        "self",
        "static",
        "switch",
        "throw",
        "trait",
        "true",
        "try",
        "unset",
        "use",
        "var",
        "while",
        "xor",
        "yield"
    ];

    /**
     * {@inheritdoc}
     */
    public function apply(CompletionContext $context, CompletionReporter $reporter)
    {
        if ($context->isObjectContext()) {
            return;
        }
        $range = $context->getReplacementRange();
        foreach (self::KEYWORDS as $keyword) {
            $reporter->report($keyword, CompletionItemKind::KEYWORD, $keyword, $range);
        }
    }
}
