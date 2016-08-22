<?php

namespace LanguageServer\Protocol;

/**
 * Represents the signature of something callable. A signature
 * can have a label, like a function-name, a doc-comment, and
 * a set of parameters.
 */
class SignatureInformation
{
    /**
     * The label of this signature. Will be shown in
     * the UI.
     *
     * @var string
     */
    public $label;

    /**
     * The human-readable doc-comment of this signature. Will be shown
     * in the UI but can be omitted.
     *
     * @var string|null
     */
    public $documentation;

    /**
     * The parameters of this signature.
     *
     * @var ParameterInformation[]|null
     */
    public $parameters;
}
