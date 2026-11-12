#!/bin/sh
# Konteyner açılışı: MariaDB'yi başlat -> veritabanını kur + örnek veri -> Apache'yi başlat
set -e
APP=/var/www/html
PORT="${PORT:-10000}"

# 1) Apache'yi Render'ın verdiği porta bağla
sed -i "s/^Listen .*/Listen ${PORT}/" /etc/apache2/ports.conf
sed -i "s/<VirtualHost \*:[0-9]*>/<VirtualHost *:${PORT}>/" /etc/apache2/sites-available/000-default.conf

# 2) MariaDB (sadece konteyner içinden erişilebilir: 127.0.0.1)
mkdir -p /run/mysqld && chown mysql:mysql /run/mysqld
if [ ! -d /var/lib/mysql/mysql ]; then
  mariadb-install-db --user=mysql --datadir=/var/lib/mysql > /dev/null
fi
mariadbd --user=mysql --datadir=/var/lib/mysql --bind-address=127.0.0.1 --skip-name-resolve \
         --innodb-buffer-pool-size=32M --performance-schema=OFF --character-set-server=utf8mb4 \
         --collation-server=utf8mb4_turkish_ci &
i=0
until mariadb-admin ping --silent 2>/dev/null; do
  i=$((i + 1)); [ "$i" -gt 60 ] && echo "MariaDB başlatılamadı" && exit 1; sleep 1
done
echo "MariaDB hazır"

# 3) Uygulama kullanıcısı ve rastgele şifre
DB_PASS=$(head -c 18 /dev/urandom | od -An -tx1 | tr -d ' \n')
mariadb -uroot <<SQL
CREATE DATABASE IF NOT EXISTS kampus_takas CHARACTER SET utf8mb4 COLLATE utf8mb4_turkish_ci;
CREATE USER IF NOT EXISTS 'kampus'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
CREATE USER IF NOT EXISTS 'kampus'@'localhost' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'kampus'@'127.0.0.1' IDENTIFIED BY '${DB_PASS}';
ALTER USER 'kampus'@'localhost' IDENTIFIED BY '${DB_PASS}';
GRANT ALL PRIVILEGES ON kampus_takas.* TO 'kampus'@'127.0.0.1';
GRANT ALL PRIVILEGES ON kampus_takas.* TO 'kampus'@'localhost';
FLUSH PRIVILEGES;
SQL
sed "s/__DB_PASS__/${DB_PASS}/" "$APP/docker/config.docker.php" > "$APP/backend/config/config.php"
chown www-data:www-data "$APP/backend/config/config.php" && chmod 640 "$APP/backend/config/config.php"

# 4) Tablolar + örnek (sentetik) veri
php "$APP/backend/database/migrate.php" --fresh --seed

# 5) Web sunucusu (ön planda çalışır)
mkdir -p "$APP/backend/public/uploads/listings" && chown -R www-data:www-data "$APP/backend/public/uploads"
echo "Kampüs Takas yayında: port ${PORT}"
exec apache2-foreground
