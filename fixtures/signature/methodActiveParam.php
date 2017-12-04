<?php

class HelpClass5
{
    public function method(string $param = "", int $count = 0, bool $test = null)
    {
    }
    public function test()
    {
        $this->method();
    }
}

$a = new HelpClass5;
$a->method("asdf", 123, true);