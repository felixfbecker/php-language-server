<?php

class Foo
{
    public function bar()
    {
        return function(){
            return $this;
        };
    }
}