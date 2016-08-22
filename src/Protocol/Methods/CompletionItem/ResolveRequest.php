<?php

namespace LanguageServer\Protocol\Methods\CompletionItem\ResolveRequest;

/**
 * The request is sent from the client to the server to resolve additional
 * information for a given completion item.
 */
class CompletionItemResolveRequest
{
    /**
     * @var CompletionItem
     */
    public $params;
}
