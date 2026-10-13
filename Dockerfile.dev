# Minimal dev-only image: runs the already-installed app (vendor/ and .env
# come from the bind-mounted project directory, see docker-compose.yml) via
# `php artisan serve`. Not a production image.
FROM php:8.2-cli

RUN docker-php-ext-install pdo_sqlite

WORKDIR /var/www/html

EXPOSE 8000

CMD ["php", "artisan", "serve", "--host=0.0.0.0", "--port=8000"]
