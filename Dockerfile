FROM php:8.1-apache

# SQLite desteğini etkinleştir
RUN apt-get update && apt-get install -y \
    sqlite3 \
    libsqlite3-dev \
    && docker-php-ext-install pdo pdo_sqlite

# Apache mod_rewrite'ı etkinleştir
RUN a2enmod rewrite

# Çalışma dizinini ayarla
WORKDIR /var/www/html

# Projeyi kopyala
COPY . /var/www/html/

# Database klasörü için yazma izni ver
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# SQLite veritabanını başlat (eğer yoksa)
RUN if [ ! -f /var/www/html/database.sqlite ]; then \
    touch /var/www/html/database.sqlite && \
    chown www-data:www-data /var/www/html/database.sqlite && \
    chmod 664 /var/www/html/database.sqlite; \
    fi

# Port 80'i aç
EXPOSE 80

# Apache'yi başlat
CMD ["apache2-foreground"]