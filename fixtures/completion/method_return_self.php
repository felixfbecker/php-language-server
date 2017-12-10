<?php

class FooClass {
    public function foo(): self {
        return $this;
    }
}

$fc = new FooClass();
$foo = $fc->foo();
$foo->
