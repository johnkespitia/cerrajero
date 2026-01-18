FROM php:8.1-apache

# Actualizar repositorios e instalar dependencias básicas
RUN apt-get update && apt-get install -y \
    apt-utils \
    curl \
    vim \
    && rm -rf /var/lib/apt/lists/*

# Habilitar mod_rewrite de Apache
RUN a2enmod rewrite

# Instalar dependencias del sistema
RUN apt-get update && apt-get install -y \
    libmcrypt-dev \
    libicu-dev \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libldb-dev \
    libldap2-dev \
    libxml2-dev \
    libssl-dev \
    libxslt-dev \
    libpq-dev \
    postgresql-client \
    mariadb-client \
    libsqlite3-dev \
    libsqlite3-0 \
    libkrb5-dev \
    libpspell-dev \
    aspell-en \
    aspell-de \
    libtidy-dev \
    libsnmp-dev \
    libonig-dev \
    libcurl4-openssl-dev \
    libbz2-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# Instalar Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/bin/ --filename=composer

# Configurar extensiones que requieren configuración especial
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-configure ldap --with-libdir=lib/x86_64-linux-gnu

# Instalar extensiones PHP que necesitan ser compiladas
RUN docker-php-ext-install -j$(nproc) \
    intl \
    gd \
    soap \
    ftp \
    xsl \
    bcmath \
    calendar \
    dba \
    ldap \
    sockets \
    pdo \
    mbstring \
    pgsql \
    pdo_pgsql \
    pdo_mysql \
    pdo_sqlite \
    mysqli \
    curl \
    exif \
    fileinfo \
    gettext \
    opcache \
    pcntl \
    phar \
    posix \
    pspell \
    simplexml \
    xmlwriter \
    bz2 \
    zip

# Instalar mcrypt (versión compatible con PHP 8.1)
RUN pecl install mcrypt-1.0.6 \
    && docker-php-ext-enable mcrypt

# Verificar instalación de mbstring
RUN php -r "var_dump(mb_ereg_match('^99.*', '123456'));" \
    && php -r "var_dump(mb_ereg_match('^12.*', '123456'));"

# Copiar configuración de Apache
COPY ./config/default-host.conf /etc/apache2/sites-available/000-default.conf

# Limpiar caché de apt
RUN apt-get clean && rm -rf /var/lib/apt/lists/*