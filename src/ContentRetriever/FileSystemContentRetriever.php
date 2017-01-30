<?php
declare(strict_types = 1);

namespace LanguageServer\ContentRetriever;

use Rx\Observable;
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
     * @return Observable Emits the content as a string
     */
    public function retrieve(string $uri): Observable
    {
        return Observable::just(file_get_contents(uriToPath($uri)));
    }
}
