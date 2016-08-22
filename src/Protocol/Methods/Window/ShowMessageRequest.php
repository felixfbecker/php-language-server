<?php

namespace LanguageServer\Protocol\Methods\Window;

use LanguageServer\Protocol\Request;

/**
 * The show message request is sent from a server to a client to ask the client to display a particular message in the
 * user interface. In addition to the show message notification the request allows to pass actions and to wait for an
 * answer from the client.
 */
class ShowMessageRequest extends Request
{
    /**
     * @var ShowMessageRequestParams
     */
    public $params;
}
