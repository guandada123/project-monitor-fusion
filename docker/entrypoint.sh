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
    #
    # 2026-11-14 决策记录：**故意不加交互式人工确认**。
    # 理由：容器启动没有 TTY，任何 read/confirm 都会让 php-fpm 永远等下去 ——
    # 「等人工放行」= 服务直接不可用，比迁移失败更糟。可行的替代（环境变量开关）
    # 会引入「忘记手动迁移 → 代码与库结构不一致」的新风险，同样更危险。
    # 因此保留自动迁移，改为把失败变成**显式可见的致命错误**：
    # set -e 本来就会退出，但日志里只有 artisan 的原始输出，容易被 restart
    # 循环掩盖成「容器起不来」。这里补一行归因明确的 FATAL。
    echo "→ 运行数据库迁移..."
    if ! php artisan migrate --force; then
        echo "❌ FATAL: 数据库迁移失败，容器退出。请查看上方 artisan 输出；"
        echo "   若容器在 restart 循环中，用 docker compose logs --tail=80 app 定位。"
        exit 1
    fi

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
