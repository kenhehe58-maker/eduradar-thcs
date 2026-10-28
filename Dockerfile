FROM php:8.2-apache

# Cài đặt MariaDB server nội bộ, extension pdo_mysql & zip
RUN apt-get update && apt-get install -y \
    mariadb-server \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo_mysql zip \
    && rm -rf /var/lib/apt/lists/*

# Kích hoạt mod_rewrite của Apache
RUN a2enmod rewrite

# Sao chép toàn bộ mã nguồn vào thư mục web của Apache
COPY . /var/www/html/

# Phân quyền cho Apache và script entrypoint
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html \
    && chmod +x /var/www/html/entrypoint.sh

ENV PORT 80
EXPOSE 80

CMD ["/var/www/html/entrypoint.sh"]
