# Anvil nginx vhost template (Phase 1 placeholder).
#
# Phase 2 will refine this (PHP-FPM upstream, websocket support, etc.).
# Only the following variables are substituted by envsubst in lib/vhost.sh:
#   ${PROJECT}  ${DOMAIN}  ${SSL_CERT}  ${SSL_KEY}  ${PROJECT_ROOT}
server {
    listen 80;
    listen 443 ssl;
    server_name ${DOMAIN};

    ssl_certificate     ${SSL_CERT};
    ssl_certificate_key ${SSL_KEY};

    root ${PROJECT_ROOT};
    index index.php index.html;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass php:9000;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
