<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/*
 * The Completion request is sent from the client to the server to compute completion
 * items at a given cursor position. Completion items are presented in the
 * [IntelliSense](https://code.visualstudio.com/docs/editor/editingevolved#_intellisense)
 * user interface. If computing full completion items is expensive, servers can
 * additionally provide a handler for the completion item resolve request. This
 * request is sent when a completion item is selected in the user interface.
 */
class CompletionRequest extends Request
{
    /**
     * @var PositionParams
     */
    public $params;
}
