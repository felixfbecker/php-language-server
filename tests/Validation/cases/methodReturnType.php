<?php

class FooClass extends EmptyClass
{
    public function foo(): FooClass {
        return $this;
    }

    /** @return self */
    public function bar() { }

    /** @return static */
    public function baz() { }

    /** @return parent */
    public function buz() { }
}
