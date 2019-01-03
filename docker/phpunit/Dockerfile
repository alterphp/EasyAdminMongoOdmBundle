FROM php:7.2-cli

RUN docker-php-ext-install -j$(nproc) mysqli

# Install MONGO
RUN pecl install mongodb \
	&& docker-php-ext-enable mongodb

VOLUME ["/app"]
WORKDIR /app
