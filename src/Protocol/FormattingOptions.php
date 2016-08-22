<?php

namespace LanguageServer\Protocol;

/**
 * Value-object describing what options formatting should use.
 */
class FormattingOptions
{
    /**
     * Size of a tab in spaces.
     *
     * @var int
     */
    public $tabSize;

    /**
     * Prefer spaces over tabs.
     *
     * @var bool
     */
    public $insertSpaces;

    // Can be extended with further properties.
}
