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
        if (!$this->isValidContext($context)) {
            return;
        }
        $range = $context->getReplacementRange();
        $nodes = $context->getPhpDocument()->getDefinitions();
        foreach ($nodes as $fqn => $node) {
            if ($node instanceof \PhpParser\Node\Stmt\ClassLike) {
                $nodeRange = Range::fromNode($node);
                if ($nodeRange->includes($context->getPosition())) {
                    foreach ($nodes as $childFqn => $child) {
                        if (stripos($childFqn, $fqn) == 0 && $childFqn !== $fqn) {
                            $reporter->reportByNode($child, $range, $childFqn);
                        }
                    }
                    return;
                }
            }
        }
    }

    private function isValidContext(CompletionContext $context)
    {
        $line = $context->getLine($context->getPosition()->line);
        if (empty($line)) {
            return false;
        }
        $range = $context->getReplacementRange($context);
        if (preg_match_all('@(\$this->|self::)@', $line, $matches, PREG_OFFSET_CAPTURE)) {
            foreach ($matches[0] as $match) {
                if (($match[1] + strlen($match[0])) === $range->start->character) {
                    return true;
                }
            }
        }
        return false;
    }
}
