FROM php:8.2-zts-alpine
WORKDIR /var/www/service

RUN apk update \
    && apk add --no-cache tzdata

ENV TZ=Asia/Jakarta

COPY --from=composer/composer:latest-bin /composer /usr/bin/composer

COPY . .
RUN composer install

EXPOSE 3004

CMD ["php","artisan","serve","--host=0.0.0.0","--port=3004"]
