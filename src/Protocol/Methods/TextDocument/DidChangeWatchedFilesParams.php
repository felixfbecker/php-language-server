<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Params;

class DidChangeWatchedFilesParams extends Params
{
    /**
     * The actual file events.
     *
     * @var LanguageServer\Protocol\FileEvent[]
     */
    public $changes;
}
