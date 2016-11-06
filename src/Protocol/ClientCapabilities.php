<?php

namespace LanguageServer\Protocol;

class ClientCapabilities
{
    /**
     * The client supports workspace/xglob requests
     *
     * @var bool|null
     */
    public $xglobProvider;

    /**
     * The client supports textDocument/xcontent requests
     *
     * @var bool|null
     */
    public $xcontentProvider;
}
