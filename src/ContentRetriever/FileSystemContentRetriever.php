<?php
declare(strict_types = 1);

namespace LanguageServer\ContentRetriever;

use LanguageServer\ContentTooLargeException;
use Sabre\Event\Promise;
use function LanguageServer\uriToPath;

/**
 * Retrieves document content from the file system
 */
class FileSystemContentRetriever implements ContentRetriever
{
    /**
     * Retrieves the content of a text document identified by the URI from the file system
     *
     * @param string $uri The URI of the document
     * @return Promise <string> Resolved with the content as a string
     */
    public function retrieve(string $uri): Promise
    {
        $limit = 150000;

        $path = uriToPath($uri);
        $size = filesize($path);
        if ($size > $limit) {
            return Promise\reject(new ContentTooLargeException($uri, $size, $limit));
        }
        return Promise\resolve(file_get_contents($path));
    }
}
