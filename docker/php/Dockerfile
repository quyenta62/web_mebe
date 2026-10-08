# Local development image for the Laravel app. Also runs the Phase 1 crawler
# (../crawler_mebe, mounted at /var/www/crawler), so it needs Node.js and Chromium.
FROM php:8.4-cli

# Must match the playwright version in crawler_mebe/package.json.
ARG PLAYWRIGHT_VERSION=1.63.0
ENV PLAYWRIGHT_BROWSERS_PATH=/ms-playwright

RUN apt-get update \
    && apt-get install -y --no-install-recommends git unzip libzip-dev libicu-dev nodejs npm \
    && docker-php-ext-install pdo_mysql zip intl bcmath pcntl \
    && npx -y "playwright@${PLAYWRIGHT_VERSION}" install --with-deps chromium \
    && chmod -R a+rX "${PLAYWRIGHT_BROWSERS_PATH}" \
    && rm -rf /var/lib/apt/lists/* /root/.npm

COPY --from=composer:2 /usr/bin/composer /usr/bin/composer

WORKDIR /var/www/web
