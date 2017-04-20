<?php

class A {
    function b() {
        for ($collection = $this; null !== $collection; $collection = $collection->getParent()) {
        }
    }
}
