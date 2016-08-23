<?php

namespace LanguageServer;

/**
 * Provides method handlers for all textDocument/* methods
 */
class TextDocumentManager
{
    /**
     * The document symbol request is sent from the client to the server to list all symbols found in a given text
     * document.
     *
     * @param LanguageServer\Protocol\Methods\TextDocument\DocumentSymbolParams $params
     * @return SymbolInformation[]
     */
    public function documentSymbol(DocumentSymbolParams $params): array
    {

    }
}
