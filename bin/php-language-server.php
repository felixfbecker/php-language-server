<?php

use LanguageServer\LanguageServer;
use Sabre\Event\Loop;

require __DIR__ . '../vendor/autoload.php';

$server = new LanguageServer(new ProtocolStreamReader(STDIN), new ProtocolStreamWriter(STDOUT));

Loop\run();
