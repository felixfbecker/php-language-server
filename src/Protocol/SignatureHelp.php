<?php

namespace LanguageServer\Protocol;

/**
 * Signature help represents the signature of something
 * callable. There can be multiple signature but only one
 * active and only one active parameter.
 */
class SignatureHelp
{
    /**
     * One or more signatures.
     *
     * @var SignatureInformation[]
     */
    public $signatures;

    /**
     * The active signature.
     *
     * @var int|null
     */
    public $activeSignature;

    /**
     * The active parameter of the active signature.
     *
     * @var int|null
     */
    public $activeParameter;
    /**
     * @param SignatureInformation[] $signatures      The signatures.
     * @param int|null               $activeSignature The active signature.
     * @param int|null               $activeParameter The active parameter of the active signature.
     */
    public function __construct(array $signatures = [], int $activeSignature = null, int $activeParameter = null)
    {
        $this->signatures = $signatures;
        $this->activeSignature = $activeSignature;
        $this->activeParameter = $activeParameter;
    }
}
