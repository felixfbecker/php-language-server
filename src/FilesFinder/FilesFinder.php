<?php
declare(strict_types = 1);

namespace LanguageServer\ContentRetriever;

use Sabre\Event\Promise;

/**
 * Interface for retrieving the content of a text document
 */
interface FilesFinder
{
    /**
     * Returns all files in the workspace that match a glob.
     * If the client does not support workspace/files, it falls back to searching the file system directly.
     *
     * @param string $glob
     * @return Promise <string[]>
     */
    public function find(string $glob): Promise;
}
