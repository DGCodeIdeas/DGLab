FROM php:8.3-cli

RUN apt-get update && apt-get install -y git zip unzip
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Expose the port Render expects
EXPOSE 80

# Serve the canonical Architecture directory using PHP's built-in web server.
# Note: pre-A3, this CMD pointed at `docs/architecture/origin` (Vision A monolith, now archived at
# `archive/docs/architecture/origin/`). Per correction #1 (docs/ tree archival), it now points at the
# canonical `Architecture/` tree.
CMD ["php", "-S", "0.0.0.0:80", "-t", "Architecture"]
