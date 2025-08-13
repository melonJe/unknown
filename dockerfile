# Dockerfile
FROM php:8.2-cli AS base

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

# Install dependencies including Workerman
RUN composer require workerman/workerman && \
    composer install --no-dev --optimize-autoloader

# Copy application
COPY . .

# Final app stage
FROM base AS app

# Expose ports
EXPOSE 8000 8080

# Start both servers without Supervisor
CMD ["sh", "-c", "php server.php start -d & php -S 0.0.0.0:8000"]