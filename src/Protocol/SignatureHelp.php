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
}
