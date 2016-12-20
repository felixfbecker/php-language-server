<?php
declare(strict_types = 1);

namespace LanguageServer;

use LanguageServer\FilesFinder\FileSystemFilesFinder;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Index\StubsIndex;
use phpDocumentor\Reflection\DocBlockFactory;
use Webmozart\PathUtil\Path;
use Sabre\Uri;
use function Sabre\Event\coroutine;

foreach ([__DIR__ . '/../../../autoload.php', __DIR__ . '/../autoload.php', __DIR__ . '/../vendor/autoload.php'] as $file) {
    if (file_exists($file)) {
        require $file;
        break;
    }
}

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

            $stubsLocation = Path::canonicalize(__DIR__ . '/../vendor/JetBrains/phpstorm-stubs');
            $uris = yield $finder->find("$stubsLocation/**/*.php");

            foreach ($uris as $uri) {
                echo "Parsing $uri\n";
                $content = yield $contentRetriever->retrieve($uri);

                // Change URI to phpstubs://
                $parts = Uri\parse($uri);
                $parts['path'] = Path::makeRelative($parts['path'], $stubsLocation);
                $parts['scheme'] = 'phpstubs';
                $uri = Uri\build($parts);

                $document = new PhpDocument($uri, $content, $index, $parser, $docBlockFactory, $definitionResolver);
            }

            echo "Saving Index\n";

            $index->save();

            echo "Finished\n";
        })->wait();
    }
}
