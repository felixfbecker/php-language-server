<?php
declare(strict_types = 1);

namespace LanguageServer\Completion;

use LanguageServer\Protocol\ {
    CompletionItem,
    Range,
    Position,
    TextEdit,
    CompletionItemKind,
    CompletionList
};
use LanguageServer\Completion\Strategies\ {
    KeywordsStrategy,
    VariablesStrategy,
    ClassMembersStrategy,
    GlobalElementsStrategy
};
use LanguageServer\PhpDocument;
use PhpParser\Node;

class CompletionReporter
{
    /**
     * @var \LanguageServer\Protocol\CompletionItem
     */
    private $completionItems;

    /**
     * @var \LanguageServer\Completion\ICompletionStrategy
     */
    private $strategies;

    private $context;

    public function __construct(PhpDocument $phpDocument)
    {
        $this->context = new CompletionContext($phpDocument);
        $this->strategies = [
            new KeywordsStrategy(),
            new VariablesStrategy(),
            new ClassMembersStrategy(),
            new GlobalElementsStrategy()
        ];
    }

    public function complete(Position $position)
    {
        $this->completionItems = [];
        $this->context->setPosition($position);
        foreach ($this->strategies as $strategy) {
            $strategy->apply($this->context, $this);
        }
    }

    public function reportByNode(Node $node, Range $editRange, string $fqn = null)
    {
        if (!$node) {
            return;
        }

        if ($node instanceof \PhpParser\Node\Stmt\Property) {
            foreach ($node->props as $prop) {
                $this->reportByNode($prop, $editRange, $fqn);
            }
        } else if ($node instanceof \PhpParser\Node\Stmt\ClassConst) {
            foreach ($node->consts as $const) {
                $this->reportByNode($const, $editRange, $fqn);
            }
        } else {
            $this->report($node->name, CompletionItemKind::fromNode($node), $node->name, $editRange, $fqn);
        }
    }

    public function report(string $label, int $kind, string $insertText, Range $editRange, string $fqn = null)
    {
        $item = new CompletionItem();
        $item->label = $label;
        $item->kind = $kind;
        $item->textEdit = new TextEdit($editRange, $insertText);
        $item->data = $fqn;

        $this->completionItems[] = $item;
    }

    /**
     *
     * @return CompletionList
     */
    public function getCompletionList(): CompletionList
    {
        $completionList = new CompletionList();
        $completionList->isIncomplete = false;
        $completionList->items = $this->completionItems;
        return $completionList;
    }
}
