<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

/**
 * Params for the CodeActionRequest
 */
class CodeActionParams extends Params
{
    /**
     * The document in which the command was invoked.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The range for which the command was invoked.
     *
     * @var LanguageServer\Protocol\Range
     */
    public $range;

    /**
     * Context carrying additional information.
     *
     * @var LanguageServer\Protocol\CodeActionContext
     */
    public $context;
}
