<?php
declare(strict_types = 1);

namespace LanguageServer\Protocol;

class SymbolDescriptor extends SymbolInformation
{
    /**
     * The fully qualified structural element name, a globally unique identifier for the symbol.
     *
     * @var string
     */
    public $fqsen;

    /**
     * A package from the composer.lock file or the contents of the composer.json
     * Example: https://github.com/composer/composer/blob/master/composer.lock#L10
     * Available fields may differ
     *
     * @var object|null
     */
    public $package;

    /**
     * @param string $fqsen   The fully qualified structural element name, a globally unique identifier for the symbol.
     * @param object $package A package from the composer.lock file or the contents of the composer.json
     */
    public function __construct(string $fqsen = null, $package = null)
    {
        $this->fqsen = $fqsen;
        $this->package = $package;
    }
}
