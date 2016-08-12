<?php

use LanguageServer\LanguageServer;
use Sabre\Event\Loop;

require __DIR__ . '../vendor/autoload.php';

$server = new LanguageServer(STDIN, STDOUT);
$server->run();
