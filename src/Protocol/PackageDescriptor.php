<?php
declare(strict_types = 1);

namespace LanguageServer\Protocol;

/**
 * Uniquely identifies a Composer package
 */
class PackageDescriptor
{
    /**
     * The package name
     *
     * @var string
     */
    public $name;

    /**
     * @param string $name The package name
     */
    public function __construct(string $name = null)
    {
        $this->name = $name;
    }
}
