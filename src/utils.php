<?php

namespace LanguageServer;

/**
 * Recursively Searches files with matching filename, starting at $path. 
 *
 * @param string $path
 * @param string $pattern
 * @return array
 */
function findFilesRecursive(string $path, string $pattern): array {
    $dir = new \RecursiveDirectoryIterator($path);
    $ite = new \RecursiveIteratorIterator($dir);
    $files = new \RegexIterator($ite, $pattern, \RegexIterator::GET_MATCH);
    $fileList = array();
    foreach($files as $file) {
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
function pathToUri(string $filepath): string {
    return 'file://'.($filepath[0] == '/' || $filepath[0] == '\\' ? '' : '/').str_replace('\\', '/', $filepath);
}