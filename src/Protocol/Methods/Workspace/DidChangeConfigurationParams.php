<?php

namespace LanguageServer\Protocol\Workspace;

use LanguageServer\Protocol\Params;

class DidChangeConfigurationParams extends Params
{
    /**
     * The actual changed settings
     *
     * @var mixed
     */
    public $settings;
}
