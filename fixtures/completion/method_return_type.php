<?php

class FooClass {
    public function foo(): FooClass {
        return $this;
    }
}

$fc = new FooClass();
$foo = $fc->foo();
$foo->