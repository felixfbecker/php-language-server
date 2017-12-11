<?php

namespace Foo;

class Test
{
    /**
     * Constructor comment goes here
     *
     * @param string $first  First param
     * @param int    $second Second param
     * @param Test   $third  Third param with a longer description
     */
    public function __construct(string $first, int $second, Test $third)
    {
    }

    /**
     * Function doc
     *
     * @param SomethingElse $a A param with a different doc type
     * @param int|null      $b Param with default value
     */
    public function foo(\DateTime $a, int $b = null)
    {
    }

    public static function bar($a)
    {
    }

    /**
     * Method with no params
     */
    public function baz()
    {
    }
}

/**
 * @param int  $i Global function param one
 * @param bool $b Default false param
 * @param Test|null ...$things Test things
 */
function foo(int $i, bool $b = false, Test ...$things = null)
{
}

$t = new Test();
$t = new Test(1, );
$t->foo();
$t->foo(1,  
$t->foo(1,);
$t->baz();

foo(
    1,
    foo(1, 2,  
);

Test::bar();

new $foo();
new $foo(1, );

new NotExist();
