<?php
declare(strict_types = 1);

namespace LanguageServer\ContentRetriever;

use LanguageServer\LanguageClient;
use Sabre\Event\Promise;

/**
 * Retrieves file content from the client through a textDocument/xcontent request
 */
class ClientFilesFinder implements FilesFinder
{
    /**
     * @var LanguageClient
     */
    private $client;

    /**
     * @param LanguageClient $client
     */
    public function __construct(LanguageClient $client)
    {
        $this->client = $client;
    }

    /**
     * Returns all files in the workspace that match a glob.
     * If the client does not support workspace/files, it falls back to searching the file system directly.
     *
     * @param string $glob
     * @return Promise <string[]> The URIs
     */
    private function find(string $glob): Promise
    {
        return $this->client->workspace->xfiles()->then(function (array $textDocuments) {
            $uris = [];
            foreach ($textDocuments as $textDocument) {
                $path = Uri\parse($textDocument->uri)['path'];
                if (Glob::match($path, $pattern)) {
                    $uris[] = $textDocument->uri;
                }
            }
            return $uris;
        });
    }
}
