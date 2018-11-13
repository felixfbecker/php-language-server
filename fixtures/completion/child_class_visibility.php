<?php

class ThisChildClass extends TestClass
{

    public function canSeeMethod()
    {
        $this->
    }
}

class Foo extends Bar
{

    public function getRandom()
    {
        $this->c;
        return random_bytes(25);
    }
}

class Bar
{

    private $test;
    protected $seeme;

    public function __construct()
    {
        $this->test = 'Basic';
    }

    public function getTest()
    {
        return $this->test;
    }

    private function cantSee()
    {

    }

    protected function canSee($arg)
    {
        # code...
    }
}

$foo = new Foo();
$foo->getRandom();
