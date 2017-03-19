<?php

namespace HELLO {

    /**
     * Does something really cool!
     */
    function world() {

    }

    \HELLO;
}

namespace {

    define('HELLO', true);

    HELLO;

    HELLO\world();
}
