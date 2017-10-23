#!/bin/bash
set -e

docker build -t felixfbecker/php-language-server:${TRAVIS_TAG:1} .
docker login -e="$DOCKER_EMAIL" -u="$DOCKER_USERNAME" -p="$DOCKER_PASSWORD"
docker push felixfbecker/php-language-server:${TRAVIS_TAG:1}
