<?php

namespace LanguageServer\Protocol\TextDocument;

use LanguageServer\Protocol\Params;

class DocumentSymbolParams extends Params
{
    /**
	 * The text document.
     *
     * @var LanguageServer\Protocol\TextDocumentIdentifier
	 */
	public $textDocument;
}
