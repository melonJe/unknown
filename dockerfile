# Dockerfile
FROM php:8.2-cli as base

# Install system dependencies
RUN apt-get update && apt-get install -y \
    git \
    unzip \
    libpq-dev \
    && docker-php-ext-install \
    pcntl \
    pdo_pgsql \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apt-get clean && rm -rf /var/lib/apt/lists/*

# Install Composer
RUN curl -sS https://getcomposer.org/installer | php -- \
    --install-dir=/usr/bin --filename=composer

# Set working directory
WORKDIR /var/www/html

# Copy composer files
COPY composer.json composer.lock ./

# Install dependencies
RUN composer install --no-dev --optimize-autoloader

# Copy application
COPY . .

FROM base as workerman
# Expose ports
EXPOSE 8080

# Start Workerman server
CMD ["php", "server.php", "start"]

FROM base as web
# Expose ports
EXPOSE 8000

# Start PHP built-in server
CMD ["php", "-S", "0.0.0.0:8000"]
