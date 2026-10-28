#!/bin/bash
set -e

# Khởi động MariaDB daemon
service mariadb restart

# Chờ MariaDB socket sẵn sàng
until mariadb-admin ping --silent; do
    echo "Waiting for MariaDB to start..."
    sleep 1
done

# Cấu hình user edu_user với full quyền trên database edu_radar
mariadb -e "CREATE DATABASE IF NOT EXISTS edu_radar CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
mariadb -e "CREATE USER IF NOT EXISTS 'edu_user'@'localhost' IDENTIFIED BY 'EduRadar2026!';"
mariadb -e "GRANT ALL PRIVILEGES ON edu_radar.* TO 'edu_user'@'localhost';"
mariadb -e "GRANT ALL PRIVILEGES ON *.* TO 'root'@'localhost' WITH GRANT OPTION;"
mariadb -e "FLUSH PRIVILEGES;"

# Nạp dữ liệu ban đầu nếu bảng students chưa tồn tại
TABLE_COUNT=$(mariadb -u edu_user -pEduRadar2026! -D edu_radar -sse "SELECT count(*) FROM information_schema.tables WHERE table_schema='edu_radar' AND table_name='students';")
if [ "$TABLE_COUNT" -eq 0 ]; then
    echo "Importing initial database dump..."
    mariadb -u edu_user -pEduRadar2026! edu_radar < /var/www/html/database/edu_radar_dump.sql
    echo "Database imported successfully."
fi

# Cấu hình cổng cho Apache dựa trên biến môi trường PORT của Render
if [ -n "$PORT" ]; then
    sed -i "s/80/$PORT/g" /etc/apache2/ports.conf /etc/apache2/sites-available/*.conf
fi

echo "Starting Apache..."
exec apache2-foreground
