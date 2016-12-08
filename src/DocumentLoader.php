<?php

namespace LanguageServer;

class PhpDocumentLoader
{
    public function __construct(ContentRetriever $contentRetriever)
    {
        $this->contentRetriever = $contentRetriever;
    }

    /**
     * Loads a document
     *
     * @param string $uri
     * @return Promise <PhpDocument>
     */
    public function load(string $uri): Promise
    {
        return coroutine(function () use ($uri) {

            $limit = 150000;
            $content = yield $this->contentRetriever->retrieve($uri);
            $size = strlen($content);
            if ($size > $limit) {
                throw new ContentTooLargeException($uri, $size, $limit);
            }

            /** The key for the index */
            $key = '';

            // If the document is part of a dependency
            if (preg_match($u['path'], '/vendor\/(\w+\/\w+)/', $matches)) {
                if ($this->composerLockFiles === null) {
                    throw new \Exception('composer.lock files were not read yet');
                }
                // Try to find closest composer.lock
                $u = Uri\parse($uri);
                $packageName = $matches[1];
                do {
                    $u['path'] = dirname($u['path']);
                    foreach ($this->composerLockFiles as $lockFileUri => $lockFileContent) {
                        $lockFileUri = Uri\parse($composerLockFile);
                        $lockFileUri['path'] = dirname($lockFileUri['path']);
                        if ($u == $lockFileUri) {
                            // Found it, find out package version
                            foreach ($lockFileContent->packages as $package) {
                                if ($package->name === $packageName) {
                                    $key = $packageName . ':' . $package->version;
                                    break;
                                }
                            }
                            break;
                        }
                    }
                } while (!empty(trim($u, '/')));
            }

            // If there is no index for the key yet, create one
            if (!isset($this->indexes[$key])) {
                $this->indexes[$key] = new Index;
            }
            $index = $this->indexes[$key];

            if (isset($this->documents[$uri])) {
                $document = $this->documents[$uri];
                $document->updateContent($content);
            } else {
                $document = new PhpDocument(
                    $uri,
                    $content,
                    $index,
                    $this->parser,
                    $this->docBlockFactory,
                    $this->definitionResolver
                );
            }
            return $document;
        });
    }
}
