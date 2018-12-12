# Running this container will start a language server that listens for TCP connections on port 2088
# Every connection will be run in a forked child process

FROM composer AS builder

COPY ./ /app
RUN composer install

FROM php:7-cli
LABEL maintainer="Felix Becker <felix.b@outlook.com>"

RUN docker-php-ext-configure pcntl --enable-pcntl
RUN docker-php-ext-install pcntl
COPY ./php.ini /usr/local/etc/php/conf.d/

COPY --from=builder /app /srv/phpls

WORKDIR /srv/phpls

EXPOSE 2088

CMD ["--tcp-server=0:2088"]

ENTRYPOINT ["php", "bin/php-language-server.php"]
