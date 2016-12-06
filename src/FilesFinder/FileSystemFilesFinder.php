<?php
declare(strict_types = 1);

namespace LanguageServer\FilesFinder;

use Sabre\Event\Promise;
use function LanguageServer\{uriToPath, timeout};
use Webmozart\Glob\Iterator\GlobIterator;

class FileSystemFindFinder implements FilesFinder
{
    /**
     * Returns all files in the workspace that match a glob.
     * If the client does not support workspace/files, it falls back to searching the file system directly.
     *
     * @param string $glob
     * @return Promise <string[]>
     */
    public function find(string $glob): Promise
    {
        return coroutine(function () use ($glob) {
            $uris = [];
            foreach (new GlobIterator($pattern) as $path) {
                $uris[] = pathToUri($path);
                yield timeout();
            }
            return $uris;
        });
    }
}
