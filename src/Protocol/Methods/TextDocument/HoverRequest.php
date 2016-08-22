<?php

namespace LanguageServer\Protocol\Methods\TextDocument\HoverRequest;

use LanguageServer\Protocol\Request;

class HoverRequest extends Request
{
    /**
     * @var PositionParams
     */
    public $params;
}
