<?php
class Parent1 {
  /** @return $this */
  public function foo() {
    return $this;
  }
}

class Child extends Parent1 {
  public function bar() {
    $this->foo()->q
  }
  public function qux() {

  }
}
