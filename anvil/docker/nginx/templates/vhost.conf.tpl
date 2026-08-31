# Anvil nginx vhost template (Phase 2).
#
# Rendered by anvil/lib/vhost.sh via `envsubst`. Only the following variables
# are substituted (see the envsubst allow-list in lib/vhost.sh):
#   ${PROJECT}   project name            (e.g. "demo")
#   ${DOMAIN}    project FQDN            (e.g. "demo.test")
#   ${SSL_CERT}  absolute path to cert   (host path, mounted into nginx)
#   ${SSL_KEY}   absolute path to key    (host path, mounted into nginx)
# ${PROJECT_ROOT} is also exported by the engine but the container web root is
# fixed at /var/www/${PROJECT} (the www/ volume is mounted there), so we use
# that literal path below.

# ---------------------------------------------------------------------------
# HTTP -> HTTPS redirect
# ---------------------------------------------------------------------------
server {
    listen 80;
    listen [::]:80;
    server_name ${DOMAIN};

    # Redirect everything to HTTPS. (ACME/http-01 challenges can be served
    # here later if a real CA is introduced.)
    location / {
        return 301 https://$host$request_uri;
    }
}

# ---------------------------------------------------------------------------
# HTTPS server (TLS termination + PHP-FPM)
# ---------------------------------------------------------------------------
server {
    listen 443 ssl;
    listen [::]:443 ssl;
    http2 on;

    server_name ${DOMAIN};

    ssl_certificate     ${SSL_CERT};
    ssl_certificate_key ${SSL_KEY};

    # Sane TLS defaults.
    ssl_protocols TLSv1.2 TLSv1.3;
    ssl_prefer_server_ciphers off;
    ssl_session_cache shared:SSL:10m;
    ssl_session_timeout 1d;
    ssl_session_tickets off;

    root /var/www/${PROJECT};
    index index.php index.html;

    # Front-controller style routing for PHP apps.
    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    # PHP-FPM via FastCGI (service name "php" on port 9000).
    location ~ \.php$ {
        fastcgi_pass php:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Deny access to hidden files such as .htaccess.
    location ~ /\.ht {
        deny all;
    }
}
