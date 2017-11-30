<?php

class Foo
{
    public static function bar()
    {
        return function(){
            if($this instanceof StdClass){
                return $this;
            }
        };
    }
}
