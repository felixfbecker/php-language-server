<?php
class Grand
{
    /** @return $this */
    public function foo()
    {
        return $this;
    }
}
class Parent1 extends Grand
{
}

class Child extends Parent1
{
    public function bar()
    {
        $this->foo()->q
    }
    public function qux()
    {
    }
}
