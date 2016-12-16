<?php

namespace TestNamespace;

$obj = new TestClass($a, $b, $c);
$obj->testMethod();
echo $obj->testProperty;
TestClass::staticTestMethod();
echo TestClass::$staticTestProperty;
echo TestClass::TEST_CLASS_CONST;
test_function();

$var = 123;
echo $var;

/**
 * Aute duis elit reprehenderit tempor cillum proident anim laborum eu laboris reprehenderit ea incididunt.
 *
 * @param TestClass $param Adipisicing non non cillum sint incididunt cillum enim mollit.
 * @return TestClass
 */
function whatever(TestClass $param): TestClass {
    echo $param;
}

$fn = function() use ($var) {
    echo $var;
};

echo TEST_CONST;

use function TestNamespace\test_function;

if ($abc instanceof TestInterface) {

}

// Nested expressions
$obj->testProperty->testMethod();
TestClass::$staticTestProperty[123]->testProperty;

$child = new ChildClass;
echo $child->testMethod();
