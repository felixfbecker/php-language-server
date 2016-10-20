<?php
declare(strict_types = 1);

namespace LanguageServer\Completion;

interface ICompletionStrategy
{

    /**
     *
     * @param \LanguageServer\Completion\CompletionContext $context
     * @param \LanguageServer\Completion\CompletionReporter $reporter
     */
    public function apply(CompletionContext $context, CompletionReporter $reporter);
}
