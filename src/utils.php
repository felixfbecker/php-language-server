<?php
declare(strict_types = 1);

namespace LanguageServer;

use InvalidArgumentException;

/**
 * Recursively Searches files with matching filename, starting at $path.
 *
 * @param string $path
 * @param string $pattern
 * @return array
 */
function findFilesRecursive(string $path, string $pattern): array
{
    $dir = new \RecursiveDirectoryIterator($path);
    $ite = new \RecursiveIteratorIterator($dir);
    $files = new \RegexIterator($ite, $pattern, \RegexIterator::GET_MATCH);
    $fileList = [];
    foreach ($files as $file) {
        $fileList = array_merge($fileList, $file);
    }
    return $fileList;
}

/**
 * Transforms an absolute file path into a URI as used by the language server protocol.
 *
 * @param string $filepath
 * @return string
 */
function pathToUri(string $filepath): string
{
    $filepath = trim(str_replace('\\', '/', $filepath), '/');
    $parts = explode('/', $filepath);
    // Don't %-encode the colon after a Windows drive letter
    $first = array_shift($parts);
    if (substr($first, -1) !== ':') {
        $first = urlencode($first);
    }
    $parts = array_map('urlencode', $parts);
    array_unshift($parts, $first);
    $filepath = implode('/', $parts);
    return 'file:///' . $filepath;
}

/**
 * Transforms URI into file path
 *
 * @param string $uri
 * @return string
 */
function uriToPath(string $uri)
{
    $fragments = parse_url($uri);
    if ($fragments === null || !isset($fragments['scheme']) || $fragments['scheme'] !== 'file') {
        throw new InvalidArgumentException("Not a valid file URI: $uri");
    }
    $filepath = urldecode($fragments['path']);
    if (strpos($filepath, ':') !== false) {
        if ($filepath[0] === '/') {
            $filepath = substr($filepath, 1);
        }
        $filepath = str_replace('/', '\\', $filepath);
    }
    return $filepath;
}
