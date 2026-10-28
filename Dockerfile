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

# Phân quyền cho Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

ENV PORT 80
EXPOSE 80

# Script khởi động: Khởi động MariaDB, cấp quyền root truy cập không cần mật khẩu (mysql_native_password), nạp database edu_radar, cấu hình cổng Apache và chạy foreground
CMD service mariadb start \
    && mariadb -e "ALTER USER 'root'@'localhost' IDENTIFIED VIA mysql_native_password USING PASSWORD(''); FLUSH PRIVILEGES;" \
    && mariadb -e "CREATE DATABASE IF NOT EXISTS edu_radar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;" \
    && (mariadb edu_radar < /var/www/html/database/edu_radar_dump.sql || true) \
    && sed -i "s/80/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf \
    && apache2-foreground
