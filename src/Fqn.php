<?php

/**
 * Contains pure functions for converting AST nodes from and to FQNs
 *
 * Examples of FQNs:
 *  - testFunction()
 *  - TestNamespace\TestClass
 *  - TestNamespace\TestClass::TEST_CONSTANT
 *  - TestNamespace\TestClass::staticTestProperty
 *  - TestNamespace\TestClass::testProperty
 *  - TestNamespace\TestClass::staticTestMethod()
 *  - TestNamespace\TestClass::testMethod()
 */

namespace LanguageServer\Fqn;

use PhpParser\Node;

/**
 * Returns the FQN that is referenced by a node
 *
 * @param Node $node
 * @return string|null
 */
function getReferencedFqn(Node $node)
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
        if ($node->name instanceof Node\Expr || !($node->var instanceof Node\Expr\Variable)) {
            // Cannot get definition of dynamic calls
            return null;
        }
        // Need to resolve variable to a class
        if ($node->var->name === 'this') {
            // $this resolved to the class it is contained in
            $n = $node;
            while ($n = $n->getAttribute('parentNode')) {
                if ($n instanceof Node\Stmt\Class_) {
                    if ($n->isAnonymous()) {
                        return null;
                    }
                    $name = (string)$n->namespacedName;
                    break;
                }
            }
            if (!isset($name)) {
                return null;
            }
        } else {
            // Other variables resolve to their definition
            $varDef = getVariableDefinition($node->var);
            if (!isset($varDef)) {
                return null;
            }
            if ($varDef instanceof Node\Param) {
                if (!isset($varDef->type)) {
                    // Cannot resolve to class without a type hint
                    // TODO: parse docblock
                    return null;
                }
                $name = (string)$varDef->type;
            } else if ($varDef instanceof Node\Expr\Assign) {
                if ($varDef->expr instanceof Node\Expr\New_) {
                    if (!($varDef->expr->class instanceof Node\Name)) {
                        // Cannot get definition of dynamic calls
                        return null;
                    }
                    $name = (string)$varDef->expr->class;
                } else {
                    return null;
                }
            } else {
                return null;
            }
        }
        $name .= '::' . (string)$node->name;
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
            $n = $node;
            while ($n = $n->getAttribute('parentNode')) {
                if ($n instanceof Node\Stmt\Class_) {
                    if ($n->isAnonymous()) {
                        return null;
                    }
                    if ($className === 'parent') {
                        // parent is resolved to the parent class
                        if (!isset($n->extends)) {
                            return null;
                        }
                        $className = (string)$n->extends;
                    } else {
                        $className = (string)$n->namespacedName;
                    }
                    break;
                }
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
 * Returns the assignment or parameter node where a variable was defined
 *
 * @param Node\Expr\Variable $n The variable access
 * @return Node\Expr\Assign|Node\Param|Node\Expr\ClosureUse|null
 */
function getVariableDefinition(Node\Expr\Variable $var)
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
            if ($n instanceof Node\Expr\Assign && $n->var instanceof Node\Expr\Variable && $n->var->name === $var->name) {
                return $n;
            }
        }
    } while (isset($n) && $n = $n->getAttribute('parentNode'));
    // Return null if nothing was found
    return null;
}

/**
 * Returns the fully qualified name (FQN) that is defined by a node
 *
 * @param Node $node
 * @return string|null
 */
function getDefinedFqn(Node $node)
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
