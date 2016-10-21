<?php
declare(strict_types = 1);

namespace LanguageServer\Completion\Strategies;

use LanguageServer\Completion\ {
    CompletionContext,
    CompletionReporter,
    ICompletionStrategy
};
use LanguageServer\Protocol\Range;

class ClassMembersStrategy implements ICompletionStrategy
{

    /**
     * {@inheritdoc}
     */
    public function apply(CompletionContext $context, CompletionReporter $reporter)
    {
        if (!$context->isObjectContext()) {
            return;
        }
        $range = $context->getReplacementRange();
        $nodes = $context->getPhpDocument()->getDefinitions();
        foreach ($nodes as $fqn => $node) {
            if ($node instanceof \PhpParser\Node\Stmt\ClassLike) {
                $nodeRange = Range::fromNode($node);
                if ($nodeRange->includes($context->getPosition())) {
                    foreach ($nodes as $childFqn => $child) {
                        if (stripos($childFqn, $fqn) === 0 && $childFqn !== $fqn) {
                            $reporter->reportByNode($child, $range, $childFqn);
                        }
                    }
                    return;
                }
            }
        }
    }

}
