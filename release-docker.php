<?php

$dockerEmail = getenv('DOCKER_EMAIL');
$dockerUsername = getenv('DOCKER_USERNAME');
$dockerPassword = getenv('DOCKER_PASSWORD');
$version = json_decode(file_get_contents(__DIR__ . '/package.json'))->version;

system("docker login -e=$dockerEmail -u=$dockerUsername -p=$dockerPassword");
system("docker build -t felixfbecker/php-language-server:latest .");
system("docker tag felixfbecker/php-language-server:latest felixfbecker/php-language-server:$version .");
system("docker push felixfbecker/php-language-server:$version");
system("docker push felixfbecker/php-language-server:latest");
