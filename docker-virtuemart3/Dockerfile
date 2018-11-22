FROM opentools/docker-virtuemart:j3vm3

# install the PHP extensions we need
RUN apt-get update && apt-get install -y zip unzip zlib1g-dev wget libxml2-dev nano
RUN docker-php-ext-install zip
RUN docker-php-ext-install soap

ADD files/docker-php-ext-gd.ini /usr/local/etc/php/conf.d/docker-php-ext-gd.ini
