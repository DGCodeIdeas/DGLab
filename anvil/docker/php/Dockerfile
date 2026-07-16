# Anvil — PHP 8.3 FPM image for local development.
#
# Based on the official php:8.3-fpm image and extended with the common
# extensions typical PHP apps need (MySQL, GD, intl, zip, opcache, redis)
# plus Composer (copied from the official composer:2 image in a multi-stage
# build). The container listens on port 9000 (FastCGI) by default; nginx
# reaches it as `php:9000` over the anvil_net network.

# ---------------------------------------------------------------------------
# Stage 1: base PHP-FPM with compiled extensions
# ---------------------------------------------------------------------------
FROM php:8.3-fpm AS php_base

# System dependencies required to build the PHP extensions below.
RUN apt-get update \
    && apt-get install -y --no-install-recommends \
        libpng-dev \
        libjpeg62-turbo-dev \
        libfreetype6-dev \
        libicu-dev \
        libzip-dev \
        unzip \
        git \
        ca-certificates \
    && rm -rf /var/lib/apt/lists/*

# Configure and install the bundled ("core") extensions.
RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j"$(nproc)" \
        pdo_mysql \
        mysqli \
        gd \
        intl \
        zip \
        opcache

# Install the redis extension (phpredis) from PECL and enable it.
RUN pecl install redis \
    && docker-php-ext-enable redis

# Copy a sensible opcache config (dev-friendly: fast shutdown, file checks).
RUN { \
        echo "opcache.enable=1"; \
        echo "opcache.memory_consumption=128"; \
        echo "opcache.max_accelerated_files=10000"; \
        echo "opcache.revalidate_freq=0"; \
        echo "opcache.validate_timestamps=1"; \
        echo "opcache.fast_shutdown=1"; \
    } > /usr/local/etc/php/conf.d/opcache.ini

# ---------------------------------------------------------------------------
# Stage 2: Composer binary
# ---------------------------------------------------------------------------
FROM composer:2 AS composer

# ---------------------------------------------------------------------------
# Stage 3: AWS CLI v2 (for the EC2 SSM-fetching entrypoint)
# ---------------------------------------------------------------------------
FROM amazonlinux:2023 AS awscli
RUN yum install -y curl unzip >/dev/null 2>&1 \
    && curl -sSL "https://awscli.amazonaws.com/awscli-exe-linux-x86_64.zip" -o /tmp/awscliv2.zip \
    && unzip -q /tmp/awscliv2.zip -d /tmp \
    && /tmp/aws/install --install-dir /usr/local/aws-cli --bin-dir /usr/local/bin \
    && rm -rf /tmp/awscliv2.zip /tmp/aws

# ---------------------------------------------------------------------------
# Final image
# ---------------------------------------------------------------------------
FROM php_base AS final

# Bring in the Composer executable from the composer stage.
COPY --from=composer /usr/bin/composer /usr/bin/composer

# Bring in the AWS CLI v2 (used by entrypoint-ssm.sh on EC2 to read RDS creds
# from SSM Parameter Store). Harmless for local dev — only the EC2 compose file
# overrides the entrypoint to use entrypoint-ssm.sh.
COPY --from=awscli /usr/local/aws-cli /usr/local/aws-cli
COPY --from=awscli /usr/local/bin/aws /usr/local/bin/aws

# SSM-fetching entrypoint for the EC2 php container. The local compose file
# does NOT set an entrypoint, so this script is only invoked on EC2.
COPY entrypoint-ssm.sh /usr/local/bin/entrypoint-ssm.sh
RUN chmod +x /usr/local/bin/entrypoint-ssm.sh

# PHP-FPM exposes FastCGI on 9000 by default; expose it for clarity.
EXPOSE 9000

# Run PHP-FPM in the foreground (the image's default CMD is php-fpm, but we
# state it explicitly for readability). On EC2 the compose file overrides the
# entrypoint (not the CMD) so php-fpm is still the process that runs.
CMD ["php-fpm"]
