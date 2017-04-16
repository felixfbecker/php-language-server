<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\Protocol\TolerantSymbolInformation;
use PhpParser\Node;
use PhpParser\PrettyPrinter\Standard as PrettyPrinter;
use phpDocumentor\Reflection\{
    DocBlock, DocBlockFactory, Types, Type, Fqsen, TypeResolver
};
use LanguageServer\Protocol\SymbolInformation;
use LanguageServer\Index\ReadableIndex;
use Microsoft\PhpParser as Tolerant;

trait LoggedDefinitionResolverTrait
{
    private static $logger = true;

    private static $stackLevel = 0;

    public static $maxRecursion = 0;

    /**
     * @param ReadableIndex $index
     */
    public function __construct(ReadableIndex $index)
    {
        parent::__construct($index);
        self::$logger = true;
        self::$maxRecursion = 0;
    }

    public static $times = [];

    private static $recursion = 0;

    private static $repeat = 1;

    public function logMethod($methodName, $param1, $param2 = -1) {
        $callStr = "FUNCTION: $methodName(";
        self::$recursion++;
        self::$maxRecursion = max(self::$recursion, self::$maxRecursion);
        if ($param2 !== -1) {
            if (self::$logger === true) {
                $callStr .= $this->getString($param1) . ", " . $this->getString($param2) . ")\n";
                echo str_repeat("\t", self::$recursion) . $callStr;
            }
            $start = microtime(true);
            for ($i = 0; $i < self::$repeat; $i++) {

                $result = parent::$methodName($param1, $param2);
            }

            $end = microtime(true) - $start;

        } else {
            if (self::$logger === true) {
                $callStr .= $this->getString($param1) . ")\n";
                echo str_repeat("\t", self::$recursion) . $callStr;
            }
            $start = microtime(true);
            for ($i = 0; $i < self::$repeat; $i++) {
                $result = parent::$methodName($param1);
            }
            $end = microtime(true) - $start;
        }
        self::$recursion--;
        if (self::$recursion === 1) {
            if (!isset(self::$times[$methodName])) {
                self::$times[$methodName] = $end;
            } else {
                self::$times[$methodName] += $end;
            }
        }

        if (self::$logger === true) {
            if ($result instanceof Definition) {
                $resultText = $result->fqn;
            } elseif ($result instanceof DocBlock) {
                $resultText = $result->getDescription();
            } else {
                try {
                    $resultText = (string) $result;
                } catch (\Throwable $e) {
                    $resultText = "UNKNOWN";
                }
            }
            echo str_repeat("\t", self::$recursion + 1) . "> RESULT[$callStr]: " . $resultText . "\n";
        }
        return $result;
    }

    private function getString($param) {
        if ($param instanceof Tolerant\Node) {
            return "[" . $param->getNodeKindName() . "] " . \strtok($param->getText(), "\n");
        } elseif ($param instanceof Node) {
            $pretty = isset($param->name) ? (string) $param->name : "UNKNOWN";
            return "[" . $param->getType() . "] " . \strtok($pretty, "\n");
        }
        return (string)$param;
    }

    /**
     * Builds the declaration line for a given node.
     *
     *
     * @param Tolerant\Node $node
     * @return string
     */
    public function getDeclarationLineFromNode($node): string
    {
        return $this->logMethod('getDeclarationLineFromNode', $node);
    }

    /**
     * Gets the documentation string for a node, if it has one
     *
     * @param Tolerant\Node $node
     * @return string|null
     */
    public function getDocumentationFromNode($node)
    {
        return $this->logMethod('getDocumentationFromNode', $node);
    }

    function getDocBlock(Tolerant\Node $node) {
        return $this->logMethod('getDocBlock', $node);

    }

    /**
     * Create a Definition for a definition node
     *
     * @param Tolerant\Node $node
     * @param string $fqn
     * @return Definition
     */
    public function createDefinitionFromNode($node, string $fqn = null): Definition
    {
        return $this->logMethod('createDefinitionFromNode', $node, $fqn);
    }

    /**
     * Given any node, returns the Definition object of the symbol that is referenced
     *
     * @param Tolerant\Node $node Any reference node
     * @return Definition|null
     */
    public function resolveReferenceNodeToDefinition($node)
    {
//        var_dump(array_keys(self::$instance->index->getDefinitions()));
        self::$logger = false;
        return $this->logMethod('resolveReferenceNodeToDefinition', $node);
    }

    /**
     * Given any node, returns the FQN of the symbol that is referenced
     * Returns null if the FQN could not be resolved or the reference node references a variable
     *
     * @param Tolerant\Node $node
     * @return string|null
     */
    public function resolveReferenceNodeToFqn($node) {
        return $this->logMethod('resolveReferenceNodeToFqn', $node);
    }

    /**
     * Returns the assignment or parameter node where a variable was defined
     *
     * @param Node\Expr\Variable|Node\Expr\ClosureUse $var The variable access
     * @return Node\Expr\Assign|Node\Expr\AssignOp|Node\Param|Node\Expr\ClosureUse|null
     */
    public function resolveVariableToNode($var)
    {
        return $this->logMethod('resolveVariableToNode', $var);
    }

    /**
     * Given an expression node, resolves that expression recursively to a type.
     * If the type could not be resolved, returns Types\Mixed.
     *
     * @param \PhpParser\Node\Expr $expr
     * @return \phpDocumentor\Reflection\Type
     */
    public function resolveExpressionNodeToType($expr): Type
    {
        return $this->logMethod('resolveExpressionNodeToType', $expr);
    }

    /**
     * Takes any class name node (from a static method call, or new node) and returns a Type object
     * Resolves keywords like self, static and parent
     *
     * @param Tolerant\Node || Tolerant\Token $class
     * @return Type
     */
    public function resolveClassNameToType($class): Type
    {
        return $this->logMethod('resolveClassNameToType', $class);
    }

    /**
     * Returns the type a reference to this symbol will resolve to.
     * For properties and constants, this is the type of the property/constant.
     * For functions and methods, this is the return type.
     * For parameters, this is the type of the parameter.
     * For classes and interfaces, this is the class type (object).
     * For variables / assignments, this is the documented type or type the assignment resolves to.
     * Can also be a compound type.
     * If it is unknown, will be Types\Mixed.
     * Returns null if the node does not have a type.
     *
     * @param Tolerant\Node $node
     * @return \phpDocumentor\Reflection\Type|null
     */
    public function getTypeFromNode($node)
    {
        return $this->logMethod('getTypeFromNode', $node);
    }


    /**
     * Returns the fully qualified name (FQN) that is defined by a node
     * Returns null if the node does not declare any symbol that can be referenced by an FQN
     *
     * @param Tolerant\Node $node
     * @return string|null
     */
    public static function getDefinedFqn($node)
    {
        $result = parent::getDefinedFqn($node);
        if (self::$logger === true) {
            echo "FUNCTION: getDefinedFqn(" . $node->getNodeKindName() . ")\n";
            var_dump($result);
        }
        return $result;
    }

    public function printLogs() {
        var_dump(self::$times);
        var_dump(self::$maxRecursion);
    }
}
