<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\Node;
use phpDocumentor\Reflection\{Types, Type, Fqsen, TypeResolver};
use LanguageServer\Protocol\SymbolInformation;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;

class DefinitionResolver
{
    private $project;
    private $typeResolver;

    public function __construct(Project $project)
    {
        $this->project = $project;
        $this->typeResolver = new TypeResolver;
    }

    /**
     * Given any node, returns the Definition object of the symbol that is referenced
     *
     * @param Node $node Any reference node
     * @return Definition|null
     */
    public function resolveReferenceNodeToDefinition(Node $node)
    {
        // Variables are not indexed globally, as they stay in the file scope anyway
        if ($node instanceof Node\Expr\Variable) {
            // Resolve the variable to a definition node (assignment, param or closure use)
            $defNode = self::resolveVariableToNode($node);
            $def = new Definition;
            // Get symbol information from node (range, symbol kind)
            $def->symbolInformation = SymbolInformation::fromNode($defNode);
            if ($defNode instanceof Node\Param) {
                // Get parameter type
                $def->type = $this->getTypeFromNode($defNode);
            } else {
                // Resolve the type of the assignment/closure use node
                $def->type = $this->resolveExpression($defNode);
            }
            return $def;
        }
        // Other references are references to a global symbol that have an FQN
        // Find out the FQN
        $fqn = $this->resolveReferenceNodeToFqn($node);
        if ($fqn === null) {
            return null;
        }
        // Return the Definition object from the project index
        $def = $this->project->getDefinition($fqn);
        if ($def === null) {
            // If the node is a function or constant, it could be namespaced, but PHP falls back to global
            // http://php.net/manual/en/language.namespaces.fallback.php
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Expr\ConstFetch || $parent instanceof Node\Expr\FuncCall) {
                $parts = explode('\\', $fqn);
                $fqn = end($parts);
                $def =  $this->project->getDefinition($fqn);
            }
        }
        return $def;
    }

    /**
     * Given any node, returns the FQN of the symbol that is referenced
     * Returns null if the FQN could not be resolved or the reference node references a variable
     *
     * @param Node $node
     * @return string|null
     */
    public function resolveReferenceNodeToFqn(Node $node)
    {
        $parent = $node->getAttribute('parentNode');

        if (
            $node instanceof Node\Name && (
                $parent instanceof Node\Stmt\ClassLike
                || $parent instanceof Node\Param
                || $parent instanceof Node\FunctionLike
                || $parent instanceof Node\Expr\StaticCall
                || $parent instanceof Node\Expr\ClassConstFetch
                || $parent instanceof Node\Expr\StaticPropertyFetch
                || $parent instanceof Node\Expr\Instanceof_
            )
        ) {
            // For extends, implements, type hints and classes of classes of static calls use the name directly
            $name = (string)$node;
        // Only the name node should be considered a reference, not the UseUse node itself
        } else if ($parent instanceof Node\Stmt\UseUse) {
            $name = (string)$parent->name;
            $grandParent = $parent->getAttribute('parentNode');
            if ($grandParent instanceof Node\Stmt\GroupUse) {
                $name = $grandParent->prefix . '\\' . $name;
            } else if ($grandParent instanceof Node\Stmt\Use_ && $grandParent->type === Node\Stmt\Use_::TYPE_FUNCTION) {
                $name .= '()';
            }
        // Only the name node should be considered a reference, not the New_ node itself
        } else if ($parent instanceof Node\Expr\New_) {
            if (!($parent->class instanceof Node\Name)) {
                // Cannot get definition of dynamic calls
                return null;
            }
            $name = (string)$parent->class;
        } else if ($node instanceof Node\Expr\MethodCall || $node instanceof Node\Expr\PropertyFetch) {
            if ($node->name instanceof Node\Expr) {
                // Cannot get definition if right-hand side is expression
                return null;
            }
            // Get the type of the left-hand expression
            $varType = $this->resolveExpression($node->var);
            if ($varType instanceof Types\This) {
                // $this is resolved to the containing class
                $classFqn = self::getContainingClassFqn($node);
            } else if (!($varType instanceof Types\Object_) || $varType->getFqsen() === null) {
                // Left-hand expression could not be resolved to a class
                return null;
            } else {
                $classFqn = substr((string)$varType->getFqsen(), 1);
            }
            $name = $classFqn . '::' . (string)$node->name;
        } else if ($parent instanceof Node\Expr\FuncCall) {
            if ($parent->name instanceof Node\Expr) {
                return null;
            }
            $name = (string)($node->getAttribute('namespacedName') ?? $parent->name);
        } else if ($parent instanceof Node\Expr\ConstFetch) {
            $name = (string)($node->getAttribute('namespacedName') ?? $parent->name);
        } else if (
            $node instanceof Node\Expr\ClassConstFetch
            || $node instanceof Node\Expr\StaticPropertyFetch
            || $node instanceof Node\Expr\StaticCall
        ) {
            if ($node->class instanceof Node\Expr || $node->name instanceof Node\Expr) {
                // Cannot get definition of dynamic names
                return null;
            }
            $className = (string)$node->class;
            if ($className === 'self' || $className === 'static' || $className === 'parent') {
                // self and static are resolved to the containing class
                $classNode = getClosestNode($node, Node\Stmt\Class_::class);
                if ($className === 'parent') {
                    // parent is resolved to the parent class
                    if (!isset($n->extends)) {
                        return null;
                    }
                    $className = (string)$classNode->extends;
                } else {
                    $className = (string)$classNode->namespacedName;
                }
            }
            $name = (string)$className . '::' . $node->name;
        } else {
            return null;
        }
        if (
            $node instanceof Node\Expr\MethodCall
            || $node instanceof Node\Expr\StaticCall
            || $parent instanceof Node\Expr\FuncCall
        ) {
            $name .= '()';
        }
        if (!isset($name)) {
            return null;
        }
        return $name;
    }

    /**
     * Returns FQN of the class a node is contained in
     * Returns null if the class is anonymous or the node is not contained in a class
     *
     * @param Node $node
     * @return string|null
     */
    private static function getContainingClassFqn(Node $node)
    {
        $classNode = getClosestNode($node, Node\Stmt\Class_::class);
        if ($classNode->isAnonymous()) {
            return null;
        }
        return (string)$classNode->namespacedName;
    }

    /**
     * Returns the assignment or parameter node where a variable was defined
     *
     * @param Node\Expr\Variable $n The variable access
     * @return Node\Expr\Assign|Node\Param|Node\Expr\ClosureUse|null
     */
    public static function resolveVariableToNode(Node\Expr\Variable $var)
    {
        $n = $var;
        // Traverse the AST up
        do {
            // If a function is met, check the parameters and use statements
            if ($n instanceof Node\FunctionLike) {
                foreach ($n->getParams() as $param) {
                    if ($param->name === $var->name) {
                        return $param;
                    }
                }
                // If it is a closure, also check use statements
                if ($n instanceof Node\Expr\Closure) {
                    foreach ($n->uses as $use) {
                        if ($use->var === $var->name) {
                            return $use;
                        }
                    }
                }
                break;
            }
            // Check each previous sibling node for a variable assignment to that variable
            while ($n->getAttribute('previousSibling') && $n = $n->getAttribute('previousSibling')) {
                if (
                    ($n instanceof Node\Expr\Assign || $n instanceof Node\Expr\AssignOp)
                    && $n->var instanceof Node\Expr\Variable && $n->var->name === $var->name
                ) {
                    return $n;
                }
            }
        } while (isset($n) && $n = $n->getAttribute('parentNode'));
        // Return null if nothing was found
        return null;
    }

    /**
     * Given an expression node, resolves that expression recursively to a type.
     * If the type could not be resolved, returns Types\Mixed.
     *
     * @param Node\Expr $expr
     * @return Type
     */
    private function resolveExpression(Node\Expr $expr): Type
    {
        if ($expr instanceof Node\Expr\Variable) {
            if ($expr->name === 'this') {
                return new Types\This;
            }
            // Find variable definition
            $defNode = $this->resolveVariableToNode($expr);
            if ($defNode instanceof Node\Expr) {
                return $this->resolveExpression($defNode);
            }
            if ($defNode instanceof Node\Param) {
                return $this->getTypeFromNode($defNode);
            }
        }
        if ($expr instanceof Node\Expr\FuncCall) {
            // Find the function definition
            if ($expr->name instanceof Node\Expr) {
                // Cannot get type for dynamic function call
                return new Types\Mixed;
            }
            $fqn = (string)($expr->getAttribute('namespacedName') ?? $expr->name);
            return $this->project->getDefinition($fqn)->type;
        }
        if ($expr instanceof Node\Expr\ConstFetch) {
            if (strtolower((string)$expr->name) === 'true' || strtolower((string)$expr->name) === 'false') {
                return new Types\Boolean;
            }
            // Resolve constant
            $fqn = (string)($expr->getAttribute('namespacedName') ?? $expr->name);
            return $this->project->getDefinition($fqn)->type;
        }
        if ($expr instanceof Node\Expr\MethodCall) {
            // Resolve object
            $objType = $this->resolveExpression($expr->var);
            if (!($objType instanceof Types\Object_) || $objType->getFqsen() === null || $expr->name instanceof Node\Expr) {
                // Need the class FQN of the object
                return new Types\Mixed;
            }
            $fqn = (string)$objType->getFqsen() . '::' . $expr->name . '()';
            return $this->project->getDefinition($fqn)->type;
        }
        if ($expr instanceof Node\Expr\PropertyFetch) {
            // Resolve object
            $objType = $this->resolveExpression($expr->var);
            if (!($objType instanceof Types\Object_) || $objType->getFqsen() === null || $expr->name instanceof Node\Expr) {
                // Need the class FQN of the object
                return new Types\Mixed;
            }
            $fqn = (string)$objType->getFqsen() . '::' . $expr->name;
            return $this->project->getDefinition($fqn)->type;
        }
        if ($expr instanceof Node\Expr\StaticCall) {
            if ($expr->class instanceof Node\Expr || $expr->name instanceof Node\Expr) {
                // Need the FQN
                return new Types\Mixed;
            }
            $fqn = (string)$expr->class . '::' . $expr->name . '()';
        }
        if ($expr instanceof Node\Expr\StaticPropertyFetch || $expr instanceof Node\Expr\ClassConstFetch) {
            if ($expr->class instanceof Node\Expr || $expr->name instanceof Node\Expr) {
                // Need the FQN
                return new Types\Mixed;
            }
            $fqn = (string)$expr->class . '::' . $expr->name;
        }
        if ($expr instanceof Node\Expr\New_) {
            if ($expr->class instanceof Node\Expr) {
                return new Types\Mixed;
            }
            if ($expr->class instanceof Node\Stmt\Class_) {
                // Anonymous class
                return new Types\Object;
            }
            if ((string)$expr->class === 'self') {
                return new Types\Object_;
            }
            return new Types\Object_(new Fqsen('\\' . (string)$expr->class));
        }
        if ($expr instanceof Node\Expr\Clone_ || $expr instanceof Node\Expr\Assign) {
            return $this->resolveExpression($expr->expr);
        }
        if ($expr instanceof Node\Expr\Ternary) {
            // ?:
            if ($expr->if === null) {
                return new Types\Compound([
                    $this->resolveExpression($expr->cond),
                    $this->resolveExpression($expr->else)
                ]);
            }
            // Ternary is a compound of the two possible values
            return new Types\Compound([
                $this->resolveExpression($expr->if),
                $this->resolveExpression($expr->else)
            ]);
        }
        if ($expr instanceof Node\Expr\BinaryOp\Coalesce) {
            // ?? operator
            return new Types\Compound([
                $this->resolveExpression($expr->left),
                $this->resolveExpression($expr->right)
            ]);
        }
        if (
            $expr instanceof Node\Expr\InstanceOf_
            || $expr instanceof Node\Expr\Cast\Bool_
            || $expr instanceof Node\Expr\BooleanNot
            || $expr instanceof Node\Expr\Empty_
            || $expr instanceof Node\Expr\Isset_
            || $expr instanceof Node\Expr\BinaryOp\Greater
            || $expr instanceof Node\Expr\BinaryOp\GreaterOrEqual
            || $expr instanceof Node\Expr\BinaryOp\Smaller
            || $expr instanceof Node\Expr\BinaryOp\SmallerOrEqual
            || $expr instanceof Node\Expr\BinaryOp\BooleanAnd
            || $expr instanceof Node\Expr\BinaryOp\BooleanOr
            || $expr instanceof Node\Expr\BinaryOp\LogicalAnd
            || $expr instanceof Node\Expr\BinaryOp\LogicalOr
            || $expr instanceof Node\Expr\BinaryOp\LogicalXor
            || $expr instanceof Node\Expr\BinaryOp\NotEqual
            || $expr instanceof Node\Expr\BinaryOp\NotIdentical
        ) {
            return new Types\Boolean_;
        }
        if (
            $expr instanceof Node\Expr\Concat
            || $expr instanceof Node\Expr\Cast\String_
            || $expr instanceof Node\Expr\BinaryOp\Concat
            || $expr instanceof Node\Expr\AssignOp\Concat
            || $expr instanceof Node\Expr\Scalar\String_
            || $expr instanceof Node\Expr\Scalar\Encapsed
            || $expr instanceof Node\Expr\Scalar\EncapsedStringPart
            || $expr instanceof Node\Expr\Scalar\MagicConst\Class_
            || $expr instanceof Node\Expr\Scalar\MagicConst\Dir
            || $expr instanceof Node\Expr\Scalar\MagicConst\Function_
            || $expr instanceof Node\Expr\Scalar\MagicConst\Method
            || $expr instanceof Node\Expr\Scalar\MagicConst\Namespace_
            || $expr instanceof Node\Expr\Scalar\MagicConst\Trait_
        ) {
            return new Types\String_;
        }
        if (
            $expr instanceof Node\Expr\BinaryOp\Minus
            || $expr instanceof Node\Expr\BinaryOp\Plus
            || $expr instanceof Node\Expr\BinaryOp\Pow
            || $expr instanceof Node\Expr\BinaryOp\Mul
            || $expr instanceof Node\Expr\AssignOp\Minus
            || $expr instanceof Node\Expr\AssignOp\Plus
            || $expr instanceof Node\Expr\AssignOp\Pow
            || $expr instanceof Node\Expr\AssignOp\Mul
        ) {
            if (
                resolveType($expr->left) instanceof Types\Integer_
                && resolveType($expr->right) instanceof Types\Integer_
            ) {
                return new Types\Integer;
            }
            return new Types\Float_;
        }
        if (
            $expr instanceof Node\Scalar\LNumber
            || $expr instanceof Node\Expr\Cast\Int_
            || $expr instanceof Node\Expr\Scalar\MagicConst\Line
            || $expr instanceof Node\Expr\BinaryOp\Spaceship
            || $expr instanceof Node\Expr\BinaryOp\BitwiseAnd
            || $expr instanceof Node\Expr\BinaryOp\BitwiseOr
            || $expr instanceof Node\Expr\BinaryOp\BitwiseXor
        ) {
            return new Types\Integer;
        }
        if (
            $expr instanceof Node\Expr\BinaryOp\Div
            || $expr instanceof Node\Expr\DNumber
            || $expr instanceof Node\Expr\Cast\Double
        ) {
            return new Types\Float_;
        }
        if ($expr instanceof Node\Expr\Array_) {
            $valueTypes = [];
            $keyTypes = [];
            foreach ($expr->items as $item) {
                $valueTypes[] = $this->resolveExpression($item->value);
                $keyTypes[] = $item->key ? $this->resolveExpression($item->key) : new Types\Integer;
            }
            $valueTypes = array_unique($keyTypes);
            $keyTypes = array_unique($keyTypes);
            $valueType = count($valueTypes) > 1 ? new Types\Compound($valueTypes) : $valueTypes[0];
            $keyType = count($keyTypes) > 1 ? new Types\Compound($keyTypes) : $keyTypes[0];
            return new Types\Array_($valueTypes, $keyTypes);
        }
        if ($expr instanceof Node\Expr\ArrayDimFetch) {
            $varType = $this->resolveExpression($expr->var);
            if (!($varType instanceof Types\Array_)) {
                return new Types\Mixed;
            }
            return $varType->getValueType();
        }
        if ($expr instanceof Node\Expr\Include_) {
            // TODO: resolve path to PhpDocument and find return statement
            return new Types\Mixed;
        }
        return new Types\Mixed;
    }

      /**
     * Returns the type a reference to this symbol will resolve to.
     * For properties and constants, this is the type of the property/constant.
     * For functions and methods, this is the return type.
     * For classes and interfaces, this is the class type (object).
     * Variables are not indexed for performance reasons.
     * Can also be a compound type.
     * If it is unknown, will be Types\Mixed.
     * Returns null if the node does not have a type.
     *
     * @param Node $node
     * @return \phpDocumentor\Type|null
     */
    public function getTypeFromNode(Node $node)
    {
        if ($node instanceof Node\Param) {
            // Parameters
            $docBlock = $node->getAttribute('docBlock');
            if ($docBlock !== null && count($paramTags = $docBlock->getTagsByName('param')) > 0) {
                // Use @param tag
                return $paramTags[0]->getType();
            }
            if ($node->type !== null) {
                // Use PHP7 return type hint
                if (is_string($node->type)) {
                    // Resolve a string like "bool" to a type object
                    $type = $this->typeResolver->resolve($node->type);
                }
                $type = new Types\Object_(new Fqsen('\\' . (string)$node->type));
                if ($node->default !== null) {
                    $defaultType = $this->resolveExpression($node->default);
                    $type = new Types\Compound([$type, $defaultType]);
                }
            }
            // Unknown parameter type
            return new Types\Mixed;
        }
        if ($node instanceof Node\FunctionLike) {
            // Functions/methods
            $docBlock = $node->getAttribute('docBlock');
            if ($docBlock !== null && count($returnTags = $docBlock->getTagsByName('return')) > 0) {
                // Use @return tag
                return $returnTags[0]->getType();
            }
            if ($node->returnType !== null) {
                // Use PHP7 return type hint
                if (is_string($node->returnType)) {
                    // Resolve a string like "bool" to a type object
                    return $this->typeResolver->resolve($node->returnType);
                }
                return new Types\Object_(new Fqsen('\\' . (string)$node->returnType));
            }
            // Unknown return type
            return new Types\Mixed;
        }
        if ($node instanceof Node\Stmt\PropertyProperty || $node instanceof Node\Const_) {
            // Property or constant
            $docBlock = $node->getAttribute('parentNode')->getAttribute('docBlock');
            if ($docBlock !== null && count($varTags = $docBlock->getTagsByName('var')) > 0) {
                // Use @var tag
                return $varTags[0]->getType();
            }
            // TODO: read @property tags of class
            // TODO: Try to infer the type from default value / constant value
            // Unknown
            return new Types\Mixed;
        }
        return null;
    }

    /**
     * Returns the fully qualified name (FQN) that is defined by a node
     * Returns null if the node does not declare any symbol that can be referenced by an FQN
     *
     * @param Node $node
     * @return string|null
     */
    public static function getDefinedFqn(Node $node)
    {
        // Anonymous classes don't count as a definition
        if ($node instanceof Node\Stmt\ClassLike && isset($node->name)) {
            // Class, interface or trait declaration
            return (string)$node->namespacedName;
        } else if ($node instanceof Node\Stmt\Function_) {
            // Function: use functionName() as the name
            return (string)$node->namespacedName . '()';
        } else if ($node instanceof Node\Stmt\ClassMethod) {
            // Class method: use ClassName::methodName() as name
            $class = $node->getAttribute('parentNode');
            if (!isset($class->name)) {
                // Ignore anonymous classes
                return null;
            }
            return (string)$class->namespacedName . '::' . (string)$node->name . '()';
        } else if ($node instanceof Node\Stmt\PropertyProperty) {
            // Property: use ClassName::propertyName as name
            $class = $node->getAttribute('parentNode')->getAttribute('parentNode');
            if (!isset($class->name)) {
                // Ignore anonymous classes
                return null;
            }
            return (string)$class->namespacedName . '::' . (string)$node->name;
        } else if ($node instanceof Node\Const_) {
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Stmt\Const_) {
                // Basic constant: use CONSTANT_NAME as name
                return (string)$node->namespacedName;
            }
            if ($parent instanceof Node\Stmt\ClassConst) {
                // Class constant: use ClassName::CONSTANT_NAME as name
                $class = $parent->getAttribute('parentNode');
                if (!isset($class->name) || $class->name instanceof Node\Expr) {
                    return null;
                }
                return (string)$class->namespacedName . '::' . $node->name;
            }
        }
    }
}
