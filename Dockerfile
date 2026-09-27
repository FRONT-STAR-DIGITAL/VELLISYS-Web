FROM php:8.3-apache

# Extensions Vellisys needs (MySQL, uploads, zip/PDF helpers, HTTPS clients)
RUN apt-get update && apt-get install -y --no-install-recommends \
        libzip-dev \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        unzip \
        git \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) \
        mysqli \
        gd \
        zip \
        intl \
        opcache \
    && a2enmod rewrite headers expires deflate \
    && rm -rf /var/lib/apt/lists/*

# Allow .htaccess (www redirects, caching) to work
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf

# Quiet Apache ServerName warning
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# PHP defaults: desk app + production OPcache (Dokploy rebuild refreshes bytecode)
RUN { \
      echo 'upload_max_filesize=64M'; \
      echo 'post_max_size=64M'; \
      echo 'memory_limit=256M'; \
      echo 'max_execution_time=120'; \
      echo 'session.cookie_secure=0'; \
      echo 'session.cookie_httponly=1'; \
      echo 'realpath_cache_size=4096K'; \
      echo 'realpath_cache_ttl=600'; \
    } > /usr/local/etc/php/conf.d/vellisys.ini \
 && { \
      echo 'opcache.enable=1'; \
      echo 'opcache.enable_cli=0'; \
      echo 'opcache.memory_consumption=128'; \
      echo 'opcache.interned_strings_buffer=16'; \
      echo 'opcache.max_accelerated_files=20000'; \
      echo 'opcache.validate_timestamps=0'; \
      echo 'opcache.revalidate_freq=0'; \
      echo 'opcache.save_comments=1'; \
      echo 'opcache.fast_shutdown=1'; \
      echo 'opcache.jit=1255'; \
      echo 'opcache.jit_buffer_size=64M'; \
    } > /usr/local/etc/php/conf.d/opcache-vellisys.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

# uploads/ is gitignored — ensure the folder exists for the volume mount
RUN mkdir -p /var/www/html/uploads /var/www/html/storage/cache /var/www/html/storage/sessions \
    && chown -R www-data:www-data /var/www/html/uploads /var/www/html/storage \
    && chmod -R 775 /var/www/html/uploads /var/www/html/storage/cache /var/www/html/storage/sessions

EXPOSE 80
