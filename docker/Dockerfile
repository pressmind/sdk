FROM php:8.2-cli

RUN apt-get update && apt-get install -y --no-install-recommends \
        git \
        unzip \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libwebp-dev \
        libmagickwand-dev \
        libcurl4-openssl-dev \
        libonig-dev \
        libreadline-dev \
        zlib1g-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j$(nproc) \
        bcmath \
        gd \
        mbstring \
        pdo \
        pdo_mysql \
        zip \
        fileinfo \
        curl \
    && pecl install redis mongodb imagick \
    && docker-php-ext-enable redis mongodb imagick \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Code coverage driver (must be separate: cannot run with Xdebug enabled)
RUN pecl install pcov \
    && docker-php-ext-enable pcov \
    && php -r "exit(extension_loaded('pcov') ? 0 : 1);"

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /app

COPY composer.json composer.lock* ./
RUN composer install --no-interaction --prefer-dist --no-scripts --no-autoloader

COPY . .
RUN composer require --dev mongodb/mongodb opensearch-project/opensearch-php --no-interaction --no-scripts \
    && composer dump-autoload --optimize

RUN useradd -m -u 1000 phptest && chown -R phptest:phptest /app
USER phptest

ENTRYPOINT ["vendor/bin/phpunit"]
CMD ["--testsuite", "Unit"]
