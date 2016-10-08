<?php

namespace TestNamespace;

const TEST_CONST = 123;

class TestClass implements TestInterface
{
    const TEST_CLASS_CONST = 123;
    public static $staticTestProperty;
    public $testProperty;

    public static function staticTestMethod()
    {

    }

    public function testMethod($testParameter)
    {
        $testVariable = 123;
    }
}

trait TestTrait
{

}

interface TestInterface
{

}

function test_function()
{

}

new class {
    const TEST_CLASS_CONST = 123;
    public static $staticTestProperty;
    public $testProperty;

    public static function staticTestMethod()
    {

    }

    public function testMethod($testParameter)
    {
        $testVariable = 123;
    }
};
