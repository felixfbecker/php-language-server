<?php

namespace LanguageServer\Protocol\TextDocument;

/**
 * The log message notification is sent from the server to the client to ask the
 * client to log a particular message.
 */
class DidChangeParams
{
    /**
     * The document that did change. The version number points
     * to the version after all provided content changes have
     * been applied.
     *
     * @var VersionedTextDocumentIdentifier
     */
    public $textDocument;

    /**
     * The actual content changes.
     *
     * @var ContentChangeEvent[]
     */
    public $contentChanges;
}
