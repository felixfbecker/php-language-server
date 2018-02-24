<?php
declare(strict_types=1);

namespace LanguageServer\Scope;
use Microsoft\PhpParser\Node\QualifiedName;

/**
 * Contains information about variables at a point.
 */
class Scope
{
    /**
     * @var Variable|null $this
     *
     * Note that this will be set when a class is entered. It is unset again when entering a static function.
     */
    public $thisVariable;

    /**
     * @var Variable|null $this, except also set in static contexts.
     */
    public $currentClassLikeVariable;

    /**
     * @var Variable[] Variables in the scope, indexed by their names (without the dollar) and excluding $this.
     */
    public $variables = [];

    /**
     * @var string[] Maps unqualified names to fully qualified names.
     */
    public $resolvedNameCache = [];

    public function clearResolvedNameCache() {
        $this->resolvedNameCache = [];
    }

    /**
     * @return string|null
     */
    public function getResolvedName(QualifiedName $name) {
        $nameStr = (string)$name;
        if (array_key_exists($nameStr, $this->resolvedNameCache)) {
            return $this->resolvedNameCache[$nameStr];
        }
        $resolvedName = $name->getResolvedName();
        return $this->resolvedNameCache[$nameStr] = $resolvedName ? (string)$resolvedName : null;
    }
}
