<?php

namespace LanguageServer\Protocol\Methods\TextDocument;

use LanguageServer\Protocol\Request;

/**
 * The document highlight request is sent from the client to the server to resolve a document highlights for a given
 * text document position. For programming languages this usually highlights all references to the symbol scoped to this
 * file. However we kept 'textDocument/documentHighlight' and 'textDocument/references' separate requests since the
 * first one is allowed to be more fuzzy. Symbol matches usually have a DocumentHighlightKind of Read or Write whereas
 * fuzzy or textual matches use Textas the kind.
 */
class ReferencesRequest extends Request
{
    /**
     * @var PositionParams
     */
    public $params;
}
