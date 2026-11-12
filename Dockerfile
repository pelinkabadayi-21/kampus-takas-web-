# Kampüs Takas — canlı yayın imajı (Render.com, Docker)
# Tek konteynerde: Apache + PHP 8.2 (web) ve MariaDB (veritabanı).
# Ücretsiz planda disk kalıcı olmadığı için veritabanı her açılışta örnek veriyle yeniden kurulur.
FROM php:8.2-apache

# MariaDB ve fotoğraf işleme (GD) için gerekli paketler
RUN apt-get update \
 && apt-get install -y --no-install-recommends mariadb-server mariadb-client \
      libjpeg62-turbo-dev libpng-dev libwebp-dev libfreetype6-dev \
 && docker-php-ext-configure gd --with-jpeg --with-webp --with-freetype \
 && docker-php-ext-install -j"$(nproc)" pdo_mysql gd exif \
 && a2enmod rewrite headers \
 && rm -rf /var/lib/apt/lists/*

# PHP ayarları (fotoğraf yükleme sınırı 5 MB olduğu için PHP sınırları biraz üstünde)
COPY docker/php.ini /usr/local/etc/php/conf.d/kampus.ini
# .htaccess dosyalarının çalışması için
COPY docker/apache.conf /etc/apache2/conf-available/kampus.conf
RUN a2enconf kampus

COPY . /var/www/html/
RUN chmod +x /var/www/html/docker/start.sh \
 && mkdir -p /var/www/html/backend/public/uploads/listings \
 && chown -R www-data:www-data /var/www/html/backend/public/uploads

ENV PORT=10000
EXPOSE 10000
CMD ["/var/www/html/docker/start.sh"]
