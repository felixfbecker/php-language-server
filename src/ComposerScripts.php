<?php
declare(strict_types=1);

namespace LanguageServer;

use Amp\Loop;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\FilesFinder\FileSystemFilesFinder;
use LanguageServer\Index\StubsIndex;
use Microsoft\PhpParser;
use phpDocumentor\Reflection\DocBlockFactory;
use Webmozart\PathUtil\Path;
use function League\Uri\parse;

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
        Loop::run(function () {
            $index = new StubsIndex;

            $finder = new FileSystemFilesFinder;
            $contentRetriever = new FileSystemContentRetriever;
            $docBlockFactory = DocBlockFactory::createInstance();
            $parser = new PhpParser\Parser();
            $definitionResolver = new DefinitionResolver($index);

            $stubsLocation = null;
            foreach ([__DIR__ . '/../../../jetbrains/phpstorm-stubs', __DIR__ . '/../vendor/jetbrains/phpstorm-stubs'] as $dir) {
                if (file_exists($dir)) {
                    $stubsLocation = Path::canonicalize($dir);
                    break;
                }
            }
            if (!$stubsLocation) {
                throw new \Exception('jetbrains/phpstorm-stubs package not found');
            }

            $uris = yield from $finder->find("$stubsLocation/**/*.php");

            foreach ($uris as $uri) {
                echo "Parsing $uri\n";
                $content = yield from $contentRetriever->retrieve($uri);

                // Change URI to phpstubs://
                $parts = parse($uri);
                $parts['path'] = Path::makeRelative($parts['path'], $stubsLocation);
                $parts['scheme'] = 'phpstubs';

                // Create a new document and add it to $index
                new PhpDocument((string)$uri, $content, $index, $parser, $docBlockFactory, $definitionResolver);
            }

            $index->setComplete();

            echo "Saving Index\n";

            $index->save();

            echo "Finished\n";
        });
    }
}
