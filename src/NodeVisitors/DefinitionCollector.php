<?php
declare(strict_types = 1);

namespace LanguageServer\NodeVisitors;

use PhpParser\{NodeVisitorAbstract, Node};

/**
 * Collects definitions of classes, interfaces, traits, methods, properties and constants
 * Depends on ReferencesAdder and NameResolver
 */
class DefinitionCollector extends NodeVisitorAbstract
{
    /**
     * Map from fully qualified name (FQN) to Node
     * Examples of fully qualified names:
     *  - testFunction()
     *  - TestNamespace\TestClass
     *  - TestNamespace\TestClass::TEST_CONSTANT
     *  - TestNamespace\TestClass::staticTestProperty
     *  - TestNamespace\TestClass::testProperty
     *  - TestNamespace\TestClass::staticTestMethod()
     *  - TestNamespace\TestClass::testMethod()
     *
     * @var Node[]
     */
    public $definitions = [];

    public function enterNode(Node $node)
    {
        if ($node instanceof Node\Stmt\ClassLike && isset($node->name)) {
            // Class, interface or trait declaration
            $this->definitions[(string)$node->namespacedName] = $node;
        } else if ($node instanceof Node\Stmt\Function_) {
            // Function: use functioName() as the name
            $name = (string)$node->namespacedName . '()';
            $this->definitions[$name] = $node;
        } else if ($node instanceof Node\Stmt\ClassMethod) {
            // Class method: use ClassName::methodName() as name
            $class = $node->getAttribute('parentNode');
            if (!isset($class->name)) {
                // Ignore anonymous classes
                return;
            }
            $name = (string)$class->namespacedName . '::' . (string)$node->name . '()';
            $this->definitions[$name] = $node;
        } else if ($node instanceof Node\Stmt\PropertyProperty) {
            // Property: use ClassName::propertyName as name
            $class = $node->getAttribute('parentNode')->getAttribute('parentNode');
            if (!isset($class->name)) {
                // Ignore anonymous classes
                return;
            }
            $name = (string)$class->namespacedName . '::' . (string)$node->name;
            $this->definitions[$name] = $node;
        } else if ($node instanceof Node\Const_) {
            $parent = $node->getAttribute('parentNode');
            if ($parent instanceof Node\Stmt\Const_) {
                // Basic constant: use CONSTANT_NAME as name
                $name = (string)$node->namespacedName;
            } else if ($parent instanceof Node\Stmt\ClassConst) {
                // Class constant: use ClassName::CONSTANT_NAME as name
                $class = $parent->getAttribute('parentNode');
                if (!isset($class->name) || $class->name instanceof Node\Expr) {
                    return;
                }
                $name = (string)$class->namespacedName . '::' . $node->name;
            }
            $this->definitions[$name] = $node;
        }
    }
}
