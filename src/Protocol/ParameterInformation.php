<?php

namespace LanguageServer\Protocol;

/**
 * Represents a parameter of a callable-signature. A parameter can
 * have a label and a doc-comment.
 */
class ParameterInformation
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
     * Create ParameterInformation
     *
     * @param string $label         The label of this signature. Will be shown in the UI.
     * @param string $documentation The human-readable doc-comment of this signature. Will be shown in the UI but can
     *                              be omitted.
     */
    public function __construct(string $label, string $documentation = null)
    {
        $this->label = $label;
        $this->documentation = $documentation;
    }
}
