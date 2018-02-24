<?php
declare(strict_types=1);

namespace LanguageServer\Scope;

use phpDocumentor\Reflection\Type;
use Microsoft\PhpParser\Node;

/**
 * Contains information about a single variable.
 */
class Variable
{
    /**
     * @var Type
     */
    public $type;

    /**
     * @var Node
     */
    public $definitionNode;

    public function __construct(Type $type, Node $definitionNode)
    {
        $this->type = $type;
        $this->definitionNode = $definitionNode;
    }
}
