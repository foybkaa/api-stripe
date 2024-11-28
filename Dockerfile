# Versions
FROM dunglas/frankenphp:1.3.1-php8.3 AS frankenphp_upstream


# Base FrankenPHP image
FROM frankenphp_upstream AS frankenphp_base
WORKDIR /app

VOLUME /app/var/

# hadolint ignore=DL3008
RUN apt-get update && apt-get install -y --no-install-recommends \
	acl \
	file \
	gettext \
	git \
    make \
	&& rm -rf /var/lib/apt/lists/*

RUN set -eux; \
	install-php-extensions \
		@composer \
		apcu \
		intl \
		opcache \
		zip \
        pdo \
        pdo_pgsql \
        pgsql \
	;

# https://getcomposer.org/doc/03-cli.md#composer-allow-superuser
ENV COMPOSER_ALLOW_SUPERUSER=1


COPY --link .docker/conf.d/app.ini $PHP_INI_DIR/conf.d/
COPY --link .docker/Caddyfile /etc/caddy/Caddyfile


HEALTHCHECK --start-period=60s CMD curl -f http://localhost:2019/metrics || exit 1
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile" ]


# Dev FrankenPHP image
FROM frankenphp_base AS frankenphp_dev

ENV APP_ENV=dev XDEBUG_MODE=off

RUN mv "$PHP_INI_DIR/php.ini-development" "$PHP_INI_DIR/php.ini"

RUN set -eux; \
	install-php-extensions \
		xdebug \
	;


COPY --link .docker/conf.d/app.dev.ini $PHP_INI_DIR/conf.d/
CMD [ "frankenphp", "run", "--config", "/etc/caddy/Caddyfile", "--watch" ]