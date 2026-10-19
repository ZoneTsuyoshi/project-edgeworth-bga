# テスト用の PHP 8.4（Composer と pdo_mysql 入り）。tests/run-unit.sh と tests/run-integration.sh から使う。単体テストは DB 不要
FROM php:8.4-cli
RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip \
    && rm -rf /var/lib/apt/lists/*
RUN docker-php-ext-install pdo_mysql
COPY --from=composer:2 /usr/bin/composer /usr/bin/composer
WORKDIR /app
