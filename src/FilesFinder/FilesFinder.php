<?php
declare(strict_types = 1);

namespace LanguageServer\FilesFinder;

use Sabre\Event\Promise;

/**
 * Interface for finding files in the workspace
 */
interface FilesFinder
{
    /**
     * Returns all files in the workspace that match a glob.
     * If the client does not support workspace/xfiles, it falls back to searching the file system directly.
     *
     * @param string $glob
     * @return Promise <string[]>
     */
    public function find(string $glob, array $excludePatterns): Promise;
}

/**
 * Check if a path matches any of the globs
 * @param string $path
 * @param string[] $globs An array of globs
 * @return bool
 */
function matchGlobs(string $path, array $globs): bool
{
    foreach ($globs as $glob) {
        if (Glob::match($path, $glob)) {
            return true;
        }
    }

    return false;
}
