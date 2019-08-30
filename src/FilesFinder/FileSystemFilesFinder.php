<?php
declare(strict_types = 1);

namespace LanguageServer\FilesFinder;

use Webmozart\Glob\Iterator\GlobIterator;
use Webmozart\Glob\Glob;
use Sabre\Event\Promise;
use function Sabre\Event\coroutine;
use function LanguageServer\{pathToUri, timeout};

class FileSystemFilesFinder implements FilesFinder
{
    /**
     * Returns all files in the workspace that match a glob.
     * If the client does not support workspace/xfiles, it falls back to searching the file system directly.
     *
     * @param string $glob
     * @param string[] $excludePatterns An array of globs
     * @return Promise <string[]>
     */
    public function find(string $glob, array $excludePatterns = []): Promise
    {
        return coroutine(function () use ($glob, $excludePatterns) {
            $uris = [];
            foreach (new GlobIterator($glob) as $path) {
                // Exclude any directories that also match the glob pattern
                // Also exclude any path that matches one of the exclude patterns
                if (!is_dir($path) && !matchGlobs($path, $excludePatterns)) {
                    $uris[] = pathToUri($path);
                }

                yield timeout();
            }
            return $uris;
        });
    }
}
