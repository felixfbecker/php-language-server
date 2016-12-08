<?php

namespace LanguageServer;

use LanguageServer\FilesFinder\FileSystemFilesFinder;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use phpDocumentor\Reflection\DocBlockFactory;
use function Sabre\Event\coroutine;

coroutine(function () {

    $index = new Index;

    $finder = new FileSystemFilesFinder;
    $contentRetriever = new FileSystemContentRetriever;
    $docBlockFactory = DocBlockFactory::createInstance();
    $definitionResolver = new DefinitionResolver([$index]);

    $uris = yield $finder->find(__DIR__ . '/../vendor/JetBrains/phpstorm-stubs');

    foreach ($uris as $uri) {
        $content = $contentRetriever->retrieve($uri);
        $document = new PhpDocument($uri, $content, $index, $docBlockFactory, $definitionResolver);
    }

})->wait();
