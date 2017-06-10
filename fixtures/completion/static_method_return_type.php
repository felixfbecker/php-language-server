<?php

class FooClass {
    public static function staticFoo(): FooClass {
        return new FooClass();
    }

    public function bar() { }
}

$foo = FooClass::staticFoo();
$foo->