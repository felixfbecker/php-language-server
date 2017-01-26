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

    /**
     * @param string                      $label         The label of this signature. Will be shown in the UI.
     * @param string|null                 $documentation The human-readable doc-comment of this signature.
     * @param ParameterInformation[]|null $parameters    The parameters of this signature.
     */
    public function __construct(string $label = null, string $documentation = null, array $parameters = null)
    {
        $this->label = $label;
        $this->documentation = $documentation;
        $this->parameters = $parameters;
    }
}
