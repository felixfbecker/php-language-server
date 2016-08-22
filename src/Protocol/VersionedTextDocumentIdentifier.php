<?php

namespace LanguageServer\Protocol;

class VersionedTextDocumentIdentifier extends TextDocumentIdentifier
{
    /**
     * The version number of this document.
     *
     * @var int
     */
    public $version;
}
