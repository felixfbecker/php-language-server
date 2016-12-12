<?php
declare(strict_types = 1);

namespace LanguageServer\Index;

use LanguageServer\FilesFinder\FileSystemFilesFinder;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\Index\StubsIndex;
use phpDocumentor\Reflection\DocBlockFactory;
use Webmozart\PathUtil\Path;
use function Sabre\Event\coroutine;

/**
 * A factory for the StubIndex
 */
class StubsIndexer
{
    /**
     * @var
     */
    private $filesFinder;

    public function __construct()

    /**
     * @return Promise <StubsIndex>
     */
    public function index(): Promise
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

            file_put_contents(__DIR__ . '/../stubs', serialize($index));

            echo "Finished\n";

        })->wait();
    }
}
