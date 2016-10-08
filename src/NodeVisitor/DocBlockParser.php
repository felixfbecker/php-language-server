<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser\{NodeVisitorAbstract, Node};
use phpDocumentor\Reflection\DocBlockFactory;

/**
 * Decorates all nodes with a docBlock attribute that is an instance of phpDocumentor\Reflection\DocBlock
 */
class DocBlockParser extends NodeVisitorAbstract
{
    /**
     * @var DocBlockFactory
     */
    private $docBlockFactory;

    public function __construct(DocBlockFactory $docBlockFactory)
    {
        $this->docBlockFactory = $docBlockFactory;
    }

    public function enterNode(Node $node)
    {
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return;
        }
        $docBlock = $this->docBlockFactory->create($docComment->getText());
        $node->setAttribute('docBlock', $docBlock);
    }
}
