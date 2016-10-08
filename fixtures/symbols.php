<?php

namespace TestNamespace;

const TEST_CONST = 123;

class TestClass
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
