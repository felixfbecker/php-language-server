<?php

class HelpClass1
{
    public function method(string $param = "")
    {
    }
    public function test()
    {
        $this->method();
    }
}

$a = new HelpClass1;
$a->method();