<?php
declare(strict_types = 1);

namespace LanguageServer;

use PhpParser\Node;
use phpDocumentor\Reflection\{Types, Type, Fqsen, TypeResolver};
use LanguageServer\Protocol\SymbolInformation;
use Exception;

/**
 * Class used to represent symbols
 */
class Definition
{
    /**
     * The fully qualified name of the symbol, if it has one
     *
     * Examples of FQNs:
     *  - testFunction()
     *  - TestNamespace\TestClass
     *  - TestNamespace\TestClass::TEST_CONSTANT
     *  - TestNamespace\TestClass::staticTestProperty
     *  - TestNamespace\TestClass::testProperty
     *  - TestNamespace\TestClass::staticTestMethod()
     *  - TestNamespace\TestClass::testMethod()
     *
     * @var string
     */
    public $fqn;

    /**
     * @var Protocol\SymbolInformation
     */
    public $symbolInformation;

    /**
     * The type a reference to this symbol will resolve to.
     * For properties and constants, this is the type of the property/constant.
     * For functions and methods, this is the return type.
     * For any other declaration it will be null.
     * Can also be a compound type.
     * If it is unknown, will be Types\Mixed.
     *
     * @var \phpDocumentor\Type
     */
    public $type;

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
    public static function getTypeFromNode(Node $node)
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
                    $type = (new TypeResolver)->resolve($node->type);
                }
                $type = new Types\Object_(new Fqsen('\\' . (string)$node->type));
                if ($node->default !== null) {
                    if (is_string($node->default)) {
                        // Resolve a string like "bool" to a type object
                        $defaultType = (new TypeResolver)->resolve($node->default);
                    }
                    $defaultType = new Types\Object_(new Fqsen('\\' . (string)$node->default));
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
                    return (new TypeResolver)->resolve($node->returnType);
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
