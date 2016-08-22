<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Params;

class ReferencesParams extends Params
{
    /**
     * @var LanguageServer\Protocol\ReferencesContext
     */
    public $context;
}
