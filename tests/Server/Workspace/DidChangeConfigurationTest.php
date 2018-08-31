<?php
/**
 * Copyright (c) 2018 Jürgen Steitz
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is
 * furnished to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
 * SOFTWARE.
 */

declare(strict_types=1);

namespace LanguageServer\Tests\Server\Workspace;

use Exception;
use LanguageServer\Cache\FileSystemCache;
use LanguageServer\ContentRetriever\FileSystemContentRetriever;
use LanguageServer\DefinitionResolver;
use LanguageServer\FilesFinder\FileSystemFilesFinder;
use LanguageServer\Index\DependenciesIndex;
use LanguageServer\Index\Index;
use LanguageServer\Index\ProjectIndex;
use LanguageServer\Indexer;
use LanguageServer\LanguageClient;
use LanguageServer\Options;
use LanguageServer\PhpDocumentLoader;
use LanguageServer\Protocol\Message;
use LanguageServer\Protocol\MessageType;
use LanguageServer\Server;
use LanguageServer\Tests\MockProtocolStream;
use LanguageServer\Tests\Server\ServerTestCase;
use Sabre\Event\Promise;

class DidChangeConfigurationTest extends ServerTestCase
{
    public function testFailsWithInvalidOptionsTypeOrFormat()
    {
        $promise = new Promise;
        $sourceIndex = new Index;
        $dependenciesIndex = new DependenciesIndex;
        $projectIndex = new ProjectIndex($sourceIndex, $dependenciesIndex);
        $projectIndex->setComplete();

        $rootPath = realpath(__DIR__ . '/../../../fixtures/');
        $filesFinder = new FileSystemFilesFinder;
        $cache = new FileSystemCache;
        $initialOptions = new Options;

        $input = new MockProtocolStream;
        $output = new MockProtocolStream;

        $definitionResolver = new DefinitionResolver($projectIndex);
        $client = new LanguageClient($input, $output);
        $documentLoader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $textDocument = new Server\TextDocument($documentLoader, $definitionResolver, $client, $projectIndex);
        $indexer = new Indexer(
            $filesFinder,
            $rootPath,
            $client,
            $cache,
            $dependenciesIndex,
            $sourceIndex,
            $documentLoader,
            $initialOptions
        );
        $workspace = new Server\Workspace(
            $client,
            $projectIndex,
            $dependenciesIndex,
            $sourceIndex,
            $initialOptions,
            null,
            $documentLoader
        );

        $output->on('message', function (Message $msg) use ($promise) {
            if ($msg->body->method === 'window/showMessage' && $promise->state === Promise::PENDING) {
                $hasMessage = strpos(
                    $msg->body->params->message,
                    'Settings could not be applied. For more information see logs.'
                ) !== false;

                if ($msg->body->params->type === MessageType::ERROR && $hasMessage) {
                    $promise->fulfill(true);
                }

                if ($msg->body->params->type !== MessageType::ERROR) {
                    $promise->reject(new Exception($msg->body->params->message));
                }
            }
        });

        $settings = new \stdClass();
        $settings->php = new \stdClass();
        $settings->php->fileTypes = 'not an array';

        $workspace->didChangeConfiguration($settings);
        $this->assertTrue($promise->wait());
    }

    public function testNoChangedOptions()
    {
        $promise = new Promise;
        $sourceIndex = new Index;
        $dependenciesIndex = new DependenciesIndex;
        $projectIndex = new ProjectIndex($sourceIndex, $dependenciesIndex);
        $projectIndex->setComplete();

        $rootPath = realpath(__DIR__ . '/../../../fixtures/');
        $filesFinder = new FileSystemFilesFinder;
        $cache = new FileSystemCache;
        $initialOptions = new Options;

        $input = new MockProtocolStream;
        $output = new MockProtocolStream;

        $definitionResolver = new DefinitionResolver($projectIndex);
        $client = new LanguageClient($input, $output);
        $documentLoader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $textDocument = new Server\TextDocument($documentLoader, $definitionResolver, $client, $projectIndex);
        $indexer = new Indexer(
            $filesFinder,
            $rootPath,
            $client,
            $cache,
            $dependenciesIndex,
            $sourceIndex,
            $documentLoader,
            $initialOptions
        );
        $workspace = new Server\Workspace(
            $client,
            $projectIndex,
            $dependenciesIndex,
            $sourceIndex,
            $initialOptions,
            null,
            $documentLoader
        );

        $output->on('message', function (Message $msg) use ($promise) {
            $promise->reject(new Exception($msg->body->message));
        });

        $settings = new \stdClass();
        $settings->php = new \stdClass();
        $settings->php->fileTypes = ['.php'];

        $this->expectException(\LogicException::class);
        $workspace->didChangeConfiguration($settings);
        $promise->wait();
    }

    public function testDetectsChangedOptions()
    {
        $promise = new Promise;
        $sourceIndex = new Index;
        $dependenciesIndex = new DependenciesIndex;
        $projectIndex = new ProjectIndex($sourceIndex, $dependenciesIndex);
        $projectIndex->setComplete();

        $rootPath = realpath(__DIR__ . '/../../../fixtures/');
        $filesFinder = new FileSystemFilesFinder;
        $cache = new FileSystemCache;
        $initialOptions = new Options;

        $input = new MockProtocolStream;
        $output = new MockProtocolStream;

        $definitionResolver = new DefinitionResolver($projectIndex);
        $client = new LanguageClient($input, $output);
        $documentLoader = new PhpDocumentLoader(new FileSystemContentRetriever, $projectIndex, $definitionResolver);
        $textDocument = new Server\TextDocument($documentLoader, $definitionResolver, $client, $projectIndex);
        $indexer = new Indexer(
            $filesFinder,
            $rootPath,
            $client,
            $cache,
            $dependenciesIndex,
            $sourceIndex,
            $documentLoader,
            $initialOptions
        );
        $workspace = new Server\Workspace(
            $client,
            $projectIndex,
            $dependenciesIndex,
            $sourceIndex,
            $initialOptions,
            null,
            $documentLoader
        );

        $output->on('message', function (Message $msg) use ($promise) {
            if ($msg->body->method === 'window/showMessage' && $promise->state === Promise::PENDING) {
                $hasMessage = strpos(
                    $msg->body->params->message,
                    'You must restart your editor for the changes to take effect.'
                ) !== false;

                if ($msg->body->params->type === MessageType::INFO && $hasMessage) {
                    $promise->fulfill(true);
                }

                if ($msg->body->params->type === MessageType::ERROR) {
                    $promise->reject(new Exception($msg->body->params->message));
                }
            }
        });

        $settings = new \stdClass();
        $settings->php = new \stdClass();
        $settings->php->fileTypes = ['.php', '.php5']; // default is only .php

        $workspace->didChangeConfiguration($settings);
        $this->assertTrue($promise->wait());
    }
}
