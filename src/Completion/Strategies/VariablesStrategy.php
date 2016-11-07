<?php
declare(strict_types = 1);

namespace LanguageServer\Completion\Strategies;

use LanguageServer\Protocol\ {
    CompletionItemKind,
    Range,
    SymbolKind,
    SymbolInformation
};
use LanguageServer\Completion\ {
    ICompletionStrategy,
    CompletionContext,
    CompletionReporter
};

class VariablesStrategy implements ICompletionStrategy
{

    /**
     * {@inheritdoc}
     */
    public function apply(CompletionContext $context, CompletionReporter $reporter)
    {
        $range = $context->getReplacementRange();
        $symbols = $context->getPhpDocument()->getSymbols();
        $contextSymbol = null;
        foreach ($symbols as $symbol) {
            if ($this->isValid($symbol) && $symbol->location->range->includes($context->getPosition())) {
                $contextSymbol = $symbol;
            }
        }

        if ($contextSymbol !== null) {
            $content = '';
            $start = $contextSymbol->location->range->start;
            $end = $contextSymbol->location->range->end;
            for ($i = $start->line; $i <= $end->line; $i++) {
                $content .= $context->getLine($i);
            }
        } else {
            $content = $context->getPhpDocument()->getContent();
        }

        if (preg_match_all('@\$[a-zA-Z_\x7f-\xff][a-zA-Z0-9_\x7f-\xff]*@', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $variables = [];
            foreach ($matches[0] as $match) {
                $variables[] = $match[0];
            }

            $variables = array_unique($variables);

            foreach ($variables as $variable) {
                $reporter->report($variable, CompletionItemKind::VARIABLE, $variable, $range);
            }
        }
    }

    private function isValid(SymbolInformation $symbol)
    {
        return $symbol->kind === SymbolKind::FUNCTION || $symbol->kind === SymbolKind::METHOD;
    }

}
