# ─── Project Monitor — 多阶段 Docker 构建 ───
# Stage 1: PHP 依赖 (Composer)
FROM composer:2.8 AS composer
WORKDIR /build
COPY composer.json composer.lock ./
RUN composer install \
    --no-dev \
    --no-interaction \
    --no-progress \
    --optimize-autoloader \
    --prefer-dist \
    --audit

# Stage 2: 前端构建 (Node + Vite)
FROM node:22-alpine AS node
WORKDIR /build
COPY package.json package-lock.json postcss.config.js vite.config.js ./
RUN npm ci --production
COPY resources/ resources/
RUN npm run build

# Stage 3: 运行时 (PHP-FPM + Alpine)
FROM php:8.3-fpm-alpine AS runtime

# ─── 系统依赖 & PHP 扩展 ───
RUN apk add --no-cache \
        bash \
        curl \
        git \
        postgresql16-client \
        supervisor \
        nano \
    && docker-php-ext-install -j$(nproc) \
        pdo_pgsql \
        pgsql \
        bcmath \
        zip \
        sockets \
        opcache \
    && apk add --no-cache --virtual .build-deps \
        $PHPIZE_DEPS \
        linux-headers \
    && pecl install redis \
    && docker-php-ext-enable redis \
    && apk del .build-deps

# ─── PHP 生产配置 ───
COPY docker/php/php.ini /usr/local/etc/php/conf.d/app.ini

# ─── 时区 ───
ENV TZ=Asia/Shanghai
RUN apk add --no-cache tzdata \
    && cp /usr/share/zoneinfo/$TZ /etc/localtime \
    && echo "$TZ" > /etc/timezone

# ─── 工作目录 ───
WORKDIR /var/www

# ─── 复制应用代码 ───
COPY . .
COPY --from=composer /build/vendor/ vendor/
COPY --from=node /build/public/build/ public/build/

# ─── 目录权限 & storage link ───
RUN set -eux; \
    mkdir -p storage/app storage/framework/cache/data \
             storage/framework/sessions storage/framework/views \
             storage/logs storage/fonts bootstrap/cache; \
    chown -R www-data:www-data storage bootstrap/cache public/build; \
    chmod -R 775 storage bootstrap/cache storage/fonts; \
    php artisan storage:link --force

# ─── 入口脚本 ───
COPY docker/entrypoint.sh /usr/local/bin/entrypoint.sh
RUN chmod +x /usr/local/bin/entrypoint.sh

# ─── 健康检查 ───
HEALTHCHECK --interval=30s --timeout=5s --retries=3 \
    CMD php -r "echo @fsockopen('127.0.0.1', 9000) ? 'ok' : exit(1);" || exit 1

# ─── su-exec（用于运行时降权）───
RUN apk add --no-cache su-exec

# ─── 运行时降权：以 www-data 用户运行 ───
USER www-data

# ─── 入口 ───
ENTRYPOINT ["/usr/local/bin/entrypoint.sh"]
CMD ["php-fpm"]
