<?php
namespace RecursiveTest;

class A extends A {}

class B extends C {}
class C extends B {}

class D extends E {}
class E extends F {}
class F extends D {}

$a = new A;
$a->undef_prop = 1;

$b = new B;
$b->undef_prop = 1;

$d = new D;
$d->undef_prop = 1;
