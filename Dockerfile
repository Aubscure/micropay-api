FROM php:8.4-cli

# Install system dependencies and PostgreSQL driver
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install pdo pdo_pgsql

# Securely inject Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Define the execution environment
WORKDIR /app
COPY . .

# Install dependencies (ignoring dev packages for security and speed)
RUN composer install --no-dev --optimize-autoloader

# Execute configuration, migrations, and start the server.
# We run migrations in the CMD block so they have access to Render's live environment variables.
CMD php artisan config:cache && \
    php artisan route:cache && \
    php artisan migrate --force && \
    php artisan serve --host=0.0.0.0 --port=${PORT}
