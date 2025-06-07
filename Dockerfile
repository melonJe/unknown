# Dockerfile
FROM php:8.1-cli

# 시스템 의존성 설치 (composer, pcntl 등)
RUN apt-get update \
  && apt-get install -y unzip git \
  && docker-php-ext-install pcntl \
  && apt-get clean && rm -rf /var/lib/apt/lists/*

# Composer 설치
RUN curl -sS https://getcomposer.org/installer | php -- \
  --install-dir=/usr/bin --filename=composer

WORKDIR /var/www/html

# 의존성 선언만 복사 후 설치 (빌드 캐시 활용)
COPY composer.json composer.lock ./
RUN composer install --no-dev --optimize-autoloader

# 애플리케이션 코드 복사
COPY . .

# 기본 커맨드: Workerman 서버 실행
CMD ["php", "server.php", "start"]
