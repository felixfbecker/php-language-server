<?php

class Foo
{
    public static function bar()
    {
        return function(){
            return $this;
        };
    }
}
