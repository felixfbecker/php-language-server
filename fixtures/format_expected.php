<?php

namespace TestNamespace;

class TestClass
{
    public $testProperty;

    /**
     * @param $testParameter description
     */
    public function testMethod($testParameter)
    {
        $testVariable = 123;

        if (empty($testParameter)) {
            echo 'Empty';
        }
    }
}
