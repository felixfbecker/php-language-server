<?php
declare(strict_types = 1);

namespace LanguageServer\Server;

use LanguageServer\{LanguageClient, Project};
use LanguageServer\Index\ProjectIndex;
use LanguageServer\Protocol\SymbolInformation;

/**
 * Provides method handlers for all workspace/* methods
 */
class Workspace
{
    /**
     * The lanugage client object to call methods on the client
     *
     * @var \LanguageServer\LanguageClient
     */
    private $client;

    /**
     * The symbol index for the workspace
     *
     * @var ProjectIndex
     */
    private $index;

    /**
     * @param ProjectIndex $index Index that is searched on a workspace/symbol request
     */
    public function __construct(ProjectIndex $index, LanguageClient $client)
    {
        $this->index = $index;
        $this->client = $client;
    }

    /**
     * The workspace symbol request is sent from the client to the server to list project-wide symbols matching the query string.
     *
     * @param string $query
     * @return SymbolInformation[]
     */
    public function symbol(string $query): array
    {
        $symbols = [];
        foreach ($this->index->getDefinitions() as $fqn => $definition) {
            if ($query === '' || stripos($fqn, $query) !== false) {
                $symbols[] = $definition->symbolInformation;
            }
        }
        return $symbols;
    }
}
