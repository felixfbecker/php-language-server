<?php
declare(strict_types=1);

namespace LanguageServer\FilesFinder;

use Webmozart\Glob\Glob;
use function Amp\File\isdir;
use function LanguageServer\{pathToUri};

class FileSystemFilesFinder implements FilesFinder
{
    /**
     * Returns all files in the workspace that match a glob.
     * If the client does not support workspace/xfiles, it falls back to searching the file system directly.
     *
     * @param string $glob
     * @return \Amp\Promise <string[]>
     */
    public function find(string $glob): \Generator
    {
        $uris = [];
        $basePath = \Webmozart\Glob\Glob::getBasePath($glob);
        $pathList = [$basePath];
        while ($pathList) {
            $path = array_pop($pathList);
            if (yield isdir($path)) {
                $subFileList = yield \Amp\File\scandir($path);
                foreach ($subFileList as $subFile) {
                    $pathList[] = $path . DIRECTORY_SEPARATOR . $subFile;
                }
            } elseif (Glob::match($path, $glob)) {
                $uris[] = pathToUri($path);
            }
        }
        return $uris;
    }
}
