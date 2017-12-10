<?php

class FooClass {
    public static function staticFoo(): self {
        return new FooClass();
    }

    public function bar() { }
}

$foo = FooClass::staticFoo();
$foo->
