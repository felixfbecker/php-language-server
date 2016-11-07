<?php
declare(strict_types = 1);

namespace LanguageServer\Server;

use LanguageServer\Protocol\ {
    CompletionItem,
    TextEdit
};
use PhpParser\Node;
use LanguageServer\Project;
use phpDocumentor\Reflection\DocBlockFactory;

class CompletionItemResolver
{
    /**
     * @var \LanguageServer\Project
     */
    private $project;

    /**
     * @var \phpDocumentor\Reflection\DocBlockFactory
     */
    private $docBlockFactory;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->docBlockFactory = DocBlockFactory::createInstance();
    }

    /**
     * The request is sent from the client to the server to resolve additional information for a given completion item.
     *
     * @param string $label
     * @param int $kind
     * @param TextEdit $textEdit
     * @param string $data
     *
     * @return \LanguageServer\Protocol\CompletionItem
     */
    public function resolve($label, $kind, $textEdit, $data)
    {
        $item = new CompletionItem();
        $item->label = $label;
        $item->kind = $kind;
        $item->textEdit = $textEdit;

        if (!isset($data)) {
            return $item;
        }

        $fqn = $data;
        $phpDocument = $this->project->getDefinitionDocument($fqn);
        if (!$phpDocument) {
            return $item;
        }

        $node = $phpDocument->getDefinitionByFqn($fqn);
        if (!isset($node)) {
            return $item;
        }
        $item->detail = $this->generateItemDetails($node);
        $item->documentation = $this->getDocumentation($node);
        return $item;
    }

    private function generateItemDetails(Node $node)
    {
        if ($node instanceof \PhpParser\Node\FunctionLike) {
            return $this->generateFunctionSignature($node);
        }
        if (isset($node->namespacedName)) {
            return '\\' . ((string) $node->namespacedName);
        }
        return '';
    }

    private function generateFunctionSignature(\PhpParser\Node\FunctionLike $node)
    {
        $params = [];
        foreach ($node->getParams() as $param) {
            $label = $param->type ? ((string) $param->type) . ' ' : '';
            $label .= '$' . $param->name;
            $params[] = $label;
        }
        $signature = '(' . implode(', ', $params) . ')';
        if ($node->getReturnType()) {
            $signature .= ': ' . $node->getReturnType();
        }
        return $signature;
    }

    private function getDocumentation(Node $node)
    {
        // Get the documentation string
        $contents = '';
        $docBlock = $node->getAttribute('docBlock');
        if ($docBlock !== null) {
            $contents .= $docBlock->getSummary() . "\n\n";
            $contents .= $docBlock->getDescription();
        }
        return $contents;
    }
}
