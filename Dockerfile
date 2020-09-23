FROM richarvey/nginx-php-fpm:1.10.3
LABEL maintainer="Trackmerger"
COPY . /var/www/html
WORKDIR /var/www/html
RUN mv ./docker/nginx/default.conf /etc/nginx/sites-enabled/default.conf && \
    mv .env.example .env && \
    composer install && \
    php artisan key:generate

CMD ["/start.sh"]
