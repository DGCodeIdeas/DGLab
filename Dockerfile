FROM php:8.3-cli

# This is a placeholder Dockerfile for the rebooted Sovereign Stack.
# Since we are in the blueprinting phase, we don't have a full app yet.
# We'll just provide a simple environment to keep Render happy.

RUN apt-get update && apt-get install -y git zip unzip
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

WORKDIR /app
COPY . .

# In a real reboot, we would install dependencies here.
# For now, we just exist.
CMD ["php", "-v"]
