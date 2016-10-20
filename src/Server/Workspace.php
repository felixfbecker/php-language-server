<?php
declare(strict_types = 1);

namespace LanguageServer\Server;

use LanguageServer\{LanguageClient, Project};
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
     * The current project database
     *
     * @var Project
     */
    private $project;

    public function __construct(Project $project, LanguageClient $client)
    {
        $this->project = $project;
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
        if ($query === '') {
            return array_values($this->project->getSymbols());
        }
        $symbols = [];
        foreach ($this->project->getSymbols() as $fqn => $symbol) {
            if (stripos($fqn, $query) !== false) {
                $symbols[] = $symbol;
            }
        }
        return $symbols;
    }
}
