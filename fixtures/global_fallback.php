<?php

namespace GlobalFallback;

// Should fall back to global_symbols.php
test_function();
echo TEST_CONST;

// Should not fall back
$obj = new TestClass();
