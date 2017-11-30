<?php

class Foo
{
    public function bar()
    {
        return function(){
            if($this instanceof StdClass){
                return $this;
            }
        };
    }
}