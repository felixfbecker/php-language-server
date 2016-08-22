<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The code action request is sent from the client to the server to compute commands for a given text document and
 * range. The request is triggered when the user moves the cursor into a problem marker in the editor or presses the
 * lightbulb associated with a marker.
 */
class CodeActionRequest extends Request
{
    /**
     * @var CodeActionParams
     */
    public $params;
}
