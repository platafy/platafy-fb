FROM php:8.2-fpm-alpine

RUN apk add --no-cache     nginx     curl     libpng-dev     libzip-dev     oniguruma-dev     && docker-php-ext-install pdo_mysql zip

WORKDIR /var/www/html

COPY nginx.conf /etc/nginx/http.d/default.conf
COPY . /var/www/html/

RUN mkdir -p /run/nginx /var/www/html/uploads     && chmod -R 777 /var/www/html/uploads

EXPOSE 80

CMD ["sh", "-c", "php-fpm -D && nginx -g 'daemon off;'"]
