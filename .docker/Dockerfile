# Dockerfile
FROM php:7.4-apache

# Droits d'écriture pour www-data
RUN usermod -u 1000 www-data
RUN usermod -a -G www-data www-data
RUN usermod -a -G www-data root
RUN chmod -R 777 /var/www/

ENV COMPOSER_ALLOW_SUPERUSER=1
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
RUN composer --version

RUN apt-get update -qq && \
    apt-get install -qy \
    git \
    gnupg \
    unzip \
    zip 
RUN docker-php-ext-install -j$(nproc) opcache pdo_mysql mysqli

# Install imagick pour le redimensionement d'images
RUN apt-get install -y libmagickwand-dev --no-install-recommends && rm -rf /var/lib/apt/lists/*
RUN printf "\n" | pecl install imagick
RUN docker-php-ext-enable imagick

COPY conf/apache/000-default.conf /etc/apache2/sites-available/000-default.conf

# activer mod_rewrite et mod_header
RUN a2enmod rewrite
RUN a2enmod headers

# Mise en forme des erreurs et var_dump()
RUN pecl install xdebug
RUN docker-php-ext-enable xdebug 

# # conf pour les mails (ne fonctionne pas en l'état)
# RUN curl -Lsf 'https://storage.googleapis.com/golang/go1.8.3.linux-amd64.tar.gz' | tar -C '/usr/local' -xvzf -
# ENV PATH /usr/local/go/bin:$PATH
# RUN GOPATH=$HOME/go \
#     PATH=$PATH:$GOROOT/bin:$GOPATH/bin \
#     go get github.com/mailhog/mhsendmail
# RUN ln  ~/go/bin/mhsendmail /usr/bin/mhsendmail

# install GD pour le traitement des images
RUN apt-get install -y zlib1g-dev libpng-dev libfreetype6-dev libjpeg62-turbo-dev
RUN docker-php-ext-configure gd --with-jpeg \
    && docker-php-ext-install -j "$(nproc)" gd

# Copie des clés RSA pour autoriser composer à se connecter à Github
# COPY .ssh/id_rsa /var/www/.ssh/id_rsa
# RUN chown -R www-data:www-data /var/www/.ssh
# RUN chmod 400 /var/www/.ssh/id_rsa

# Les alias
COPY .bashrc /var/www/.bashrc
RUN chown www-data:www-data /var/www/.bashrc

WORKDIR /var/www/app