<?php

namespace LanguageServer;

use LanguageServer\FilesFinder\FileSystemFilesFinder;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Index\StubsIndex;
use phpDocumentor\Reflection\DocBlockFactory;
use Webmozart\PathUtil\Path;
use function Sabre\Event\coroutine;

require_once __DIR__ . '/../vendor/sabre/event/lib/coroutine.php';
require_once __DIR__ . '/../vendor/sabre/event/lib/Loop/functions.php';
require_once __DIR__ . '/../vendor/sabre/event/lib/Promise/functions.php';
require_once __DIR__ . '/utils.php';

class ComposerScripts
{
    public static function parseStubs()
    {
        coroutine(function () {

            $index = new StubsIndex;

            $finder = new FileSystemFilesFinder;
            $contentRetriever = new FileSystemContentRetriever;
            $docBlockFactory = DocBlockFactory::createInstance();
            $parser = new Parser;
            $definitionResolver = new DefinitionResolver($index);

            $uris = yield $finder->find(Path::canonicalize(__DIR__ . '/../vendor/JetBrains/phpstorm-stubs/**/*.php'));

            foreach ($uris as $uri) {
                echo "Parsing $uri\n";
                $content = yield $contentRetriever->retrieve($uri);
                $document = new PhpDocument($uri, $content, $index, $parser, $docBlockFactory, $definitionResolver);
            }

            echo "Saving Index\n";

            $index->save();

            echo "Finished\n";
        })->wait();
    }
}
