FROM php:8.2-fpm-alpine

# ffmpeg：手机录音转 mp3、音视频时长、视频封面；gmp/bcmath：Web Push VAPID 签名；gd：缩略图
RUN apk add --no-cache ffmpeg gmp-dev libpng-dev libjpeg-turbo-dev libwebp-dev freetype-dev libzip-dev icu-dev oniguruma-dev \
    && docker-php-ext-configure gd --with-freetype --with-jpeg --with-webp \
    && docker-php-ext-install -j"$(nproc)" gd pdo_mysql bcmath gmp opcache zip intl mbstring \
    && rm -rf /tmp/*

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

RUN { \
      echo 'upload_max_filesize=200M'; \
      echo 'post_max_size=210M'; \
      echo 'memory_limit=512M'; \
      echo 'max_execution_time=300'; \
      echo 'date.timezone=Asia/Shanghai'; \
      echo 'expose_php=Off'; \
      echo 'opcache.enable=1'; \
      echo 'opcache.validate_timestamps=0'; \
    } > /usr/local/etc/php/conf.d/memo.ini

WORKDIR /var/www/api
