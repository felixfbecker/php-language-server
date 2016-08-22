<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Params;

class DidChangeParams extends Params
{
    /**
     * The document that did change. The version int points
     * to the version after all provided content changes have
     * been applied.
     *
     * @var LanguageServer\Protocol\VersionedTextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The actual content changes.
     *
     * @var LanguageServer\Protocol\TextDocumentContentChangeEvent[]
     */
    public $contentChanges;
}
