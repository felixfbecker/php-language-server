<?php

namespace Foo;

class Bar {
    public $foo;

    /** @return Bar[] */
    public function test() { }
}

$bar = new Bar();
$bars = $bar->test();
$array1 = [new Bar(), new \stdClass()];
$array2 = ['foo' => $bar, $bar];
$array3 = ['foo' => $bar, 'baz' => $bar];

foreach ($bars as $value) {
    $v
    $value->
}

foreach ($array1 as $key => $value) {
    $
}

foreach ($array2 as $key => $value) {
    $
}

foreach ($array3 as $key => $value) {
    $
}

foreach ($bar->test() as $value) {
    $
}

foreach ($unknownArray as $member->access => $unknown) {
    $unkno

foreach ($loop as $loop) {
}
 
foreach ($loop->getArray() as $loop) {
}
