<?php

namespace LanguageServer\Protocol;

/**
 * A workspace edit represents changes to many resources managed in the workspace.
 */
class WorkspaceEdit
{
    /**
     * Holds changes to existing resources. Associative Array from URI to TextEdit
     *
     * @var TextEdit[]
     */
    public $changes;
}
