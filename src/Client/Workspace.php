<?php
declare(strict_types = 1);

namespace LanguageServer\Client;

use LanguageServer\ClientHandler;
use LanguageServerProtocol\TextDocumentIdentifier;
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
     * Returns a list of all files in a directory
     *
     * @param string $base The base directory (defaults to the workspace)
     * @return Promise <TextDocumentIdentifier[]> Array of documents
     */
    public function xfiles(string $base = null): Promise
    {
        return $this->handler->request(
            'workspace/xfiles',
            ['base' => $base]
        )->then(function (array $textDocuments) {
            return $this->mapper->mapArray($textDocuments, [], TextDocumentIdentifier::class);
        });
    }

    /**
     * The workspace/configuration request is sent from the server to the client
     * to fetch configuration settings from the client. The request can fetch
     * several configuration settings in one roundtrip. The order of the
     * returned configuration settings correspond to the order of the passed
     * ConfigurationItems (e.g. the first item in the response is the result
     * for the first configuration item in the params).
     *
     * @param  ConfigurationItem[] $items Array of configuration items
     * @return Promise <stdClass[]> Array of configuration objects
     */
    public function configuration(array $items): Promise
    {
        return $this->handler->request(
            'workspace/configuration',
            ['items' => $items]
        )->then(function (array $items) {
            return $items;
            //return $this->mapper->mapArray($items, [], ConfigurationItem::class);
        });
    }
}
