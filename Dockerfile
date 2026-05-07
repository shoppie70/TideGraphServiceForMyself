FROM php:8.0-apache

# 必要なモジュールやツールをインストール
RUN apt-get update && apt-get install -y \
    libzip-dev \
    unzip \
    && docker-php-ext-install pdo_mysql mysqli zip

# Apacheのmod_rewriteを有効化 (ルーティングなどで必要な場合)
RUN a2enmod rewrite

# Composerをインストール
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/html
