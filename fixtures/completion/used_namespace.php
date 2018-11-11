<?php

namespace Whatever;

use TestNamespace\InnerNamespace as AliasNamespace;

class IDontShowUpInCompletion {}

AliasNamespace\I;
AliasNamespace\;
