# PHP Language Server

[![Version](https://img.shields.io/packagist/v/felixfbecker/language-server.svg)](https://packagist.org/packages/felixfbecker/language-server)
[![Build Status](https://travis-ci.org/felixfbecker/php-language-server.svg?branch=master)](https://travis-ci.org/felixfbecker/php-language-server)
[![Coverage](https://codecov.io/gh/felixfbecker/php-language-server/branch/master/graph/badge.svg)](https://codecov.io/gh/felixfbecker/php-language-server)
[![Dependency Status](https://gemnasium.com/badges/github.com/felixfbecker/php-language-server.svg)](https://gemnasium.com/github.com/felixfbecker/php-language-server)
[![Minimum PHP Version](https://img.shields.io/badge/php-%3E%3D%207.0-8892BF.svg)](https://php.net/)
[![License](https://img.shields.io/packagist/l/felixfbecker/language-server.svg)](https://github.com/felixfbecker/php-language-server/blob/master/LICENSE.txt)
[![Gitter](https://badges.gitter.im/felixfbecker/php-language-server.svg)](https://gitter.im/felixfbecker/php-language-server?utm_source=badge&utm_medium=badge&utm_campaign=pr-badge)

A pure PHP implementation of the [Language Server Protocol](https://github.com/Microsoft/language-server-protocol).

![Find all symbols demo](images/documentSymbol.gif)

## Used by
 - [vscode-php-intellisense](https://github.com/felixfbecker/vscode-php-intellisense)

## Contributing

You need at least PHP 7.0 and Composer installed.
Clone the repository and run

    composer install

to install dependencies.

Run the tests with 

    vendor/bin/phpunit --bootstrap vendor/autoload.php tests

## Command line arguments

    --tcp host:port

Causes the server to use a tcp connection for communicating with the language client instead of using STDIN/STDOUT.
The server will try to connect to the specified address.

Example:

    php bin/php-language-server.php --tcp 127.0.0.1:12345

