<?php

namespace TestNamespace\InnerNamespace;

use TestNamespace\TestClass as InnerClass;

// Both of these should complete to namespace\InnerClass, for \TestNamespace\InnerNamespace\InnerClass
namespace\;
namespace\InnerCl;
