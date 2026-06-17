#!/bin/sh
# ─── Project Monitor — Docker 入口脚本 ───
set -e

# ─── 等待数据库就绪 ───
if [ "${DB_CONNECTION}" = "pgsql" ]; then
    echo "→ 等待 PostgreSQL 就绪..."
    until pg_isready -h "${DB_HOST:-postgres}" -p "${DB_PORT:-5432}" -U "${DB_USERNAME:-project_monitor}" 2>/dev/null; do
        sleep 1
    done
    echo "✓ PostgreSQL 已就绪"
fi

# ─── 初始化 (仅 app 容器执行) ───
if [ "$1" = "php-fpm" ]; then
    # 生成 APP_KEY（仅开发环境；生产环境必须由外部注入）
    if [ -z "$APP_KEY" ] || [ "$APP_KEY" = "base64:xxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxxx=" ]; then
        if [ "${APP_ENV:-production}" = "production" ]; then
            echo "❌ FATAL: APP_KEY is not set in production. Aborting."
            exit 1
        fi
        echo "→ 开发/演示环境：生成 APP_KEY..."
        # 尝试写入 .env；若不可写（www-data 无权限），则设环境变量
        if php artisan key:generate --force 2>/dev/null; then
            echo "✓ APP_KEY 已写入 .env"
        else
            GENERATED_KEY=$(php artisan key:generate --show) && export APP_KEY="$GENERATED_KEY"
            echo "✓ APP_KEY 已设为环境变量（.env 不可写）"
        fi
    fi

    # 运行迁移
    echo "→ 运行数据库迁移..."
    php artisan migrate --force

    # 初始种子 (首次部署)
    if [ ! -f /var/www/storage/app/.seeded ]; then
        echo "→ 首次部署：运行种子数据..."
        php artisan db:seed --force && touch /var/www/storage/app/.seeded
    fi

    # 缓存优化
    echo "→ 优化缓存..."
    php artisan config:cache
    php artisan route:cache
    php artisan view:cache

fi

echo "✓ 启动: $@"
exec "$@"
