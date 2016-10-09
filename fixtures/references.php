<?php

namespace TestNamespace;

$obj = new TestClass();
$obj->testMethod();
echo $obj->testProperty;
TestClass::staticTestMethod();
echo TestClass::$staticTestProperty;
echo TestClass::TEST_CLASS_CONST;
test_function();

$var = 123;
echo $var;

function whatever(TestClass $param): TestClass {
    echo $param;
}

$fn = function() use ($var) {
    echo $var;
};

echo TEST_CONST;
