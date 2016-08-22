<?php

namespace LanguageServer\Protocol\Methods\InitializeRequest;

/*
 * The initialize request is sent as the first request from the client to the server.
 */
class CompletionItemResolveRequest
{
    /**
     * @var InitializeParams
     */
    public $params;
}
