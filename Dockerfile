# =========================================================================
# ArrCal — Radarr + Sonarr unified calendar dashboard
# =========================================================================
# STAGE 1: FRONTEND BUILD (Node.js + pnpm)
# =========================================================================
FROM node:22-alpine AS frontend-build
RUN corepack enable && corepack prepare pnpm@latest --activate
WORKDIR /app
COPY pnpm-workspace.yaml pnpm-lock.yaml package.json ./
COPY frontend/package.json frontend/
RUN pnpm install --frozen-lockfile --ignore-scripts
COPY frontend/ frontend/
RUN pnpm --filter arrcal-frontend build
RUN mkdir -p /app/public && cp -r frontend/dist/* /app/public/

# =========================================================================
# STAGE 2: PRODUCTION RUNTIME (PHP 8.5 CLI Alpine)
# =========================================================================
FROM php:8.5-cli-alpine

# OPcache is built-in to PHP CLI images — enabled via docker/php.ini

# Copy built frontend SPA from Stage 1
COPY --from=frontend-build /app/public /app/public

# Runtime configuration
ARG APP_VERSION=dev
ENV APP_ENV=production \
    PORT=80 \
    RATE_LIMIT=60 \
    RATE_LIMIT_WINDOW=60 \
    APP_VERSION=$APP_VERSION

WORKDIR /app
COPY . /app

# Production PHP config: OPcache + JIT optimizations
COPY docker/php.ini /usr/local/etc/php/conf.d/99-performance.ini

# Install Composer
COPY --from=composer:2 /usr/bin/composer /usr/local/bin/composer

# Production dependencies (no dev tools)
RUN composer install --no-dev --optimize-autoloader

EXPOSE 80

CMD ["php", "bin/server"]
