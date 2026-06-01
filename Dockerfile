FROM php:8.3-cli

RUN apt-get update && apt-get install -y git zip unzip
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# Expose the port Render expects
EXPOSE 80

# Serve the Architecture directory using PHP's built-in web server
# This keeps the process alive and makes blueprints viewable
CMD ["php", "-S", "0.0.0.0:80", "-t", "Architecture"]
