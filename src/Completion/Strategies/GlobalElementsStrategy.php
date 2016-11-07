<?php
declare(strict_types = 1);

namespace LanguageServer\Completion\Strategies;

use LanguageServer\Completion\ {
    CompletionContext,
    CompletionReporter,
    ICompletionStrategy
};
use LanguageServer\Protocol\CompletionItemKind;
use LanguageServer\Protocol\SymbolInformation;
use LanguageServer\Protocol\SymbolKind;

class GlobalElementsStrategy implements ICompletionStrategy
{

    /**
     * {@inheritdoc}
     */
    public function apply(CompletionContext $context, CompletionReporter $reporter)
    {
        if ($context->isObjectContext()) {
            return;
        }
        $range = $context->getReplacementRange($context);
        $project = $context->getPhpDocument()->project;
        foreach ($project->getSymbols() as $fqn => $symbol) {
            if ($this->isValid($symbol)) {
                $kind = CompletionItemKind::fromSymbol($symbol->kind);
                $reporter->report($symbol->name, $kind, $symbol->name, $range, $fqn);
            }
        }
    }

    private function isValid(SymbolInformation $symbol)
    {
        return $symbol->kind == SymbolKind::CLASS_
            || $symbol->kind == SymbolKind::INTERFACE
            || $symbol->kind == SymbolKind::FUNCTION;
    }

}
