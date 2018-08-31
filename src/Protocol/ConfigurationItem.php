<?php
declare(strict_types = 1);

namespace LanguageServer\Protocol;

class ConfigurationItem
{
    /**
     * The scope to get the configuration section for.
     *
     * @var string|null
     */
    public $scopeUri;

    /**
     * The configuration section asked for.
     *
     * @var string|null
     */
    public $section;

    public function __construct(string $section = null, string $scopeUri = null)
    {
        $this->section = $section;
        $this->scopeUri = $scopeUri;
    }
}
