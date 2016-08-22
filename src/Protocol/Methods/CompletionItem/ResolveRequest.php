<?php

namespace LanguageServer\Protocol\Methods\CompletionItem;

use LanguageServer\Protocol\Request;

/**
 * The request is sent from the client to the server to resolve additional
 * information for a given completion item.
 */
class ResolveRequest extends Request
{
    /**
     * @var LanguageServer\Protocol\CompletionItem
     */
    public $params;
}
