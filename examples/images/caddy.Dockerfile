FROM caddy AS base
ARG SERVICE
COPY services/$SERVICE /app/services/$SERVICE
COPY index.php /app/index.php
COPY global-config.php /app/global-config.php
COPY images/Caddyfile /etc/caddy/Caddyfile
