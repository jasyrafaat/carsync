FROM php:8.2-cli

# تثبيت امتداد MongoDB
RUN apt-get update && apt-get install -y \
    libssl-dev pkg-config git unzip curl \
    && pecl install mongodb \
    && docker-php-ext-enable mongodb

# تثبيت Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# إنشاء مجلد التطبيق ونسخ الملفات
WORKDIR /app
COPY . .

# تثبيت مكتبات PHP من Composer
RUN composer install

# أمر التشغيل
CMD ["php", "websocket_server.php"]
