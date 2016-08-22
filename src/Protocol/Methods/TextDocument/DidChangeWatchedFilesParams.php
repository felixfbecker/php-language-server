<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

/**
 * The watched files notification is sent from the client to the server when the
 * client detects changes to files watched by the language client.
 */
class DidChangeWatchedFilesParams extends Params
{
    /**
     * The actual file events.
     *
     * @var FileEvent[]
     */
    public $changes;
}
