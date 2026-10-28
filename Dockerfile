FROM php:8.2-apache

# Cài đặt extension pdo_mysql & zip
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    && docker-php-ext-install pdo_mysql zip

# Kích hoạt mod_rewrite của Apache
RUN a2enmod rewrite

# Sao chép toàn bộ mã nguồn vào thư mục web của Apache
COPY . /var/www/html/

# Phân quyền cho Apache
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Cấu hình cổng động theo biến môi trường $PORT của Cloud (Render / Railway)
ENV PORT 80
EXPOSE 80

# Script khởi động Apache chạy trên đúng cổng $PORT
CMD sed -i "s/80/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf && apache2-foreground
