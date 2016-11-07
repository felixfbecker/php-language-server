<?php

namespace LanguageServer\Protocol;

class Content
{
    /**
     * The content of the text document
     *
     * @var string
     */
    public $text;

    /**
     * @param string $text The content of the text document
     */
    public function __construct(string $text = null)
    {
        $this->text = $text;
    }
}
