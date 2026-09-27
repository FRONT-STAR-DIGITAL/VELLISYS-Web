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

# PHP defaults suitable for a desk app
RUN { \
      echo 'upload_max_filesize=64M'; \
      echo 'post_max_size=64M'; \
      echo 'memory_limit=256M'; \
      echo 'max_execution_time=120'; \
      echo 'session.cookie_secure=0'; \
      echo 'session.cookie_httponly=1'; \
    } > /usr/local/etc/php/conf.d/vellisys.ini

WORKDIR /var/www/html

COPY --chown=www-data:www-data . /var/www/html

# uploads/ is gitignored — ensure the folder exists for the volume mount
RUN mkdir -p /var/www/html/uploads \
    && chown -R www-data:www-data /var/www/html/uploads \
    && chmod -R 775 /var/www/html/uploads

EXPOSE 80
