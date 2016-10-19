<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitor;

use PhpParser;
use PhpParser\{NodeVisitorAbstract, Node, Comment};
use phpDocumentor\Reflection\DocBlockFactory;
use phpDocumentor\Reflection\Types\Context;
use Exception;

/**
 * Decorates all nodes with a docBlock attribute that is an instance of phpDocumentor\Reflection\DocBlock
 */
class DocBlockParser extends NodeVisitorAbstract
{
    /**
     * @var DocBlockFactory
     */
    private $docBlockFactory;

    /**
     * The current namespace context
     *
     * @var string
     */
    private $namespace;

    /**
     * Namespace aliases in the current context
     *
     * @var string[]
     */
    private $aliases;

    /**
     * @var PhpParser\Error[]
     */
    public $errors = [];

    public function __construct(DocBlockFactory $docBlockFactory)
    {
        $this->docBlockFactory = $docBlockFactory;
    }

    public function beforeTraverse(array $nodes)
    {
        $this->namespace = '';
        $this->aliases = [];
    }

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = (string)$node->name;
        } else if ($node instanceof Node\Stmt\UseUse) {
            $this->aliases[(string)$node->name] = $node->alias;
        }
        $docComment = $node->getDocComment();
        if ($docComment === null) {
            return;
        }
        $context = new Context($this->namespace, $this->aliases);
        try {
            $docBlock = $this->docBlockFactory->create($docComment->getText(), $context);
            $node->setAttribute('docBlock', $docBlock);
        } catch (Exception $e) {
            $this->errors[] = new PhpParser\Error($e->getMessage(), [
                'startFilePos' => $docComment->getFilePos(),
                'endFilePos'   => $docComment->getFilePos() + strlen($docComment->getText()),
                'startLine'    => $docComment->getLine(),
                'endLine'      => $docComment->getLine() + preg_match_all('/[\\n\\r]/', $docComment->getText()) + 1
            ]);
        }
    }

    public function leaveNode(Node $node)
    {
        if ($node instanceof Node\Stmt\Namespace_) {
            $this->namespace = '';
            $this->aliases = [];
        }
    }
}
