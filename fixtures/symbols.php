<?php

namespace TestNamespace;

/**
 * Esse commodo excepteur pariatur Lorem est aute incididunt reprehenderit.
 *
 * @var int
 */
const TEST_CONST = 123;

/**
 * Pariatur ut laborum tempor voluptate consequat ea deserunt.
 *
 * Deserunt enim minim sunt sint ea nisi. Deserunt excepteur tempor id nostrud
 * laboris commodo ad commodo velit mollit qui non officia id. Nulla duis veniam
 * veniam officia deserunt et non dolore mollit ea quis eiusmod sit non. Occaecat
 * consequat sunt culpa exercitation pariatur id reprehenderit nisi incididunt Lorem
 * sint. Officia culpa pariatur laborum nostrud cupidatat consequat mollit.
 */
class TestClass implements TestInterface
{
    /**
     * Anim labore veniam consectetur laboris minim quis aute aute esse nulla ad.
     *
     * @var int
     */
    const TEST_CLASS_CONST = 123;

    /**
     * Lorem excepteur officia sit anim velit veniam enim.
     *
     * @var TestClass[]
     */
    public static $staticTestProperty;

    /**
     * Reprehenderit magna velit mollit ipsum do.
     *
     * @var TestClass
     */
    public $testProperty;

    /**
     * Do magna consequat veniam minim proident eiusmod incididunt aute proident.
     */
    public static function staticTestMethod()
    {
        echo self::TEST_CLASS_CONST;
    }

    /**
     * Non culpa nostrud mollit esse sunt laboris in irure ullamco cupidatat amet.
     *
     * @param TestClass $testParameter Lorem sunt velit incididunt mollit
     * @return TestClass
     */
    public function testMethod($testParameter): TestInterface
    {
        $this->testProperty = $testParameter;
    }
}

trait TestTrait
{

}

interface TestInterface
{

}

/**
 * Officia aliquip adipisicing et nulla et laboris dolore labore.
 *
 * @return void
 */
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

class ChildClass extends TestClass {}

class Example {
    public function __construct() {}
    public function __destruct() {}
}

namespace TestNamespace\InnerNamespace;

class InnerClass {
}
