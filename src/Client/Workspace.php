<?php
declare(strict_types = 1);

namespace LanguageServer\Client;

use LanguageServer\ClientHandler;
use LanguageServer\Protocol\TextDocumentIdentifier;
use Sabre\Event\Promise;
use JsonMapper;

/**
 * Provides method handlers for all workspace/* methods
 */
class Workspace
{
    /**
     * @var ClientHandler
     */
    private $handler;

    /**
     * @var JsonMapper
     */
    private $mapper;

    public function __construct(ClientHandler $handler, JsonMapper $mapper)
    {
        $this->handler = $handler;
        $this->mapper = $mapper;
    }

    /**
     * Returns a list of all files in the workspace that match a glob pattern
     *
     * @param string $pattern A glob pattern
     * @return Promise <TextDocumentIdentifier[]> Array of documents that match the glob pattern
     */
    public function xglob(string $pattern): Promise
    {
        return $this->handler->request('workspace/_glob', ['pattern' => $pattern])->then(function ($textDocuments) {
            return $this->mapper->mapArray($textDocuments, [], TextDocumentIdentifier::class);
        });
    }
}
