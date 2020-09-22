FROM richarvey/nginx-php-fpm:1.10.3

LABEL maintainer="GPS-Merger"
COPY . /var/www/html
WORKDIR /var/www/html
RUN mv .env.example .env && \
    composer install && \
    php artisan key:generate

CMD ["/start.sh"]