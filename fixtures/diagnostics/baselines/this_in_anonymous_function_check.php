<?php

$dummy = function(){
    if($this instanceof StdClass){
        return $this;
    }
};