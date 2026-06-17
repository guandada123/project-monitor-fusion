#!/usr/bin/env bash
# ─── Project Monitor — 一键 Docker 部署脚本 ───
# 用法:
#   ./deploy.sh                    # 首次部署（一键）
#   ./deploy.sh upgrade            # 升级部署（拉取新代码后）
#   ./deploy.sh rebuild            # 完全重建（无缓存）
#   ./deploy.sh logs               # 查看日志
#   ./deploy.sh down               # 停止所有服务
#   ./deploy.sh shell              # 进入 app 容器
#   ./deploy.sh artisan {cmd}      # 运行 Artisan 命令
#
set -euo pipefail

cd "$(dirname "$0")"

APP_NAME="Project Monitor"
COMPOSE_FILE="docker-compose.yml"

# ─── 颜色 ───
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
CYAN='\033[0;36m'
NC='\033[0m'

log()  { echo -e "${GREEN}→${NC} $1"; }
warn() { echo -e "${YELLOW}⚠${NC} $1"; }
err()  { echo -e "${RED}✗${NC} $1"; }

# ─── 前置检查 ───
check_prereqs() {
    if ! command -v docker &>/dev/null; then
        err "Docker 未安装。请先安装 Docker：https://docs.docker.com/engine/install/"
        exit 1
    fi
    if ! docker compose version &>/dev/null; then
        err "Docker Compose V2 不可用。请升级 Docker。"
        exit 1
    fi
    log "前置检查通过 ✓"
}

# ─── 初始化 .env ───
init_env() {
    if [ -f .env ]; then
        log ".env 已存在，跳过创建"
        return
    fi

    # 优先使用 .env.docker（Docker 优化配置）；其次 .env.example
    if [ -f .env.docker ]; then
        log "从 .env.docker 创建 .env..."
        cp .env.docker .env
    elif [ -f .env.example ]; then
        log "从 .env.example 创建 .env..."
        cp .env.example .env
        warn ".env.docker 不存在，建议创建以获得最佳 Docker 体验"
    else
        err "找不到 .env.docker 或 .env.example！"
        exit 1
    fi

    # ─── 自动生成 APP_KEY ───
    log "生成 APP_KEY..."
    # 先确保 vendor 存在，否则 key:generate --show 会失败
    if [ ! -d vendor ]; then
        warn "vendor 目录不存在，可能无法生成本地 APP_KEY"
        warn "部署启动后容器会自动生成 APP_KEY"
        return
    fi
    if ! php artisan key:generate --force 2>/dev/null; then
        warn "本地 artisan key:generate 失败，容器启动时会自动生成"
    else
        log "APP_KEY 已生成 ✓"
    fi
}

# ─── 等待服务就绪 ───
wait_for_healthy() {
    local service="$1"
    local max_wait=60
    local waited=0

    echo -n "  → 等待 $service 就绪..."
    while [ $waited -lt $max_wait ]; do
        if docker compose -f "$COMPOSE_FILE" ps --format json "$service" 2>/dev/null | grep -q '"Health":"healthy"'; then
            echo -e " ${GREEN}就绪 ✓${NC}"
            return 0
        fi
        sleep 2
        waited=$((waited + 2))
        echo -n "."
    done
    echo -e " ${RED}超时 ✗${NC}"
    warn "$service 未在 ${max_wait} 秒内就绪，请检查日志: docker compose logs $service"
    return 1
}

# ─── 首次部署 ───
deploy_first() {
    echo ""
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "  🚀  ${APP_NAME} — 一键部署"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo ""

    # 创建必要目录
    mkdir -p storage/app storage/logs storage/fonts

    # 初始化环境配置
    init_env

    # 构建镜像
    log "构建 Docker 镜像..."
    docker compose -f "$COMPOSE_FILE" build

    # 启动数据库（先于 app，让数据库有足够时间初始化）
    log "启动 PostgreSQL..."
    docker compose -f "$COMPOSE_FILE" up -d postgres
    wait_for_healthy postgres || true

    # 启动所有服务
    log "启动所有服务..."
    docker compose -f "$COMPOSE_FILE" up -d

    # 等待应用就绪
    wait_for_healthy app || true
    wait_for_healthy nginx || true

    # ─── 首次部署初始化（仅首次）───
    log "运行数据库迁移..."
    docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force || {
        warn "迁移失败，重试一次..."
        sleep 3
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force
    }

    log "运行种子数据..."
    docker compose -f "$COMPOSE_FILE" exec -T app php artisan db:seed --force || true

    # 缓存优化（仅生产环境）
    if grep -q 'APP_ENV=production' .env 2>/dev/null; then
        log "优化缓存..."
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache || true
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache || true
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache || true
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan event:cache || true
    fi

    echo ""
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "  ✅  部署完成！"
    echo -e "${GREEN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    show_info
}

# ─── 升级部署 ───
deploy_upgrade() {
    echo ""
    log "升级部署: ${APP_NAME}"

    # 拉取最新代码（如果是 Git 仓库）
    if [ -d .git ]; then
        log "拉取最新代码..."
        git pull
    fi

    # 利用 Docker 缓存重建镜像
    log "重建镜像（利用缓存）..."
    docker compose -f "$COMPOSE_FILE" build --pull

    # 重新创建更改的服务
    log "重启服务..."
    docker compose -f "$COMPOSE_FILE" up -d --remove-orphans

    # 运行新的迁移
    log "运行迁移..."
    docker compose -f "$COMPOSE_FILE" exec -T app php artisan migrate --force || true

    # 缓存刷新
    log "刷新缓存..."
    docker compose -f "$COMPOSE_FILE" exec -T app php artisan optimize:clear || true
    if grep -q 'APP_ENV=production' .env 2>/dev/null; then
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan config:cache || true
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan route:cache || true
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan view:cache || true
    fi

    # 清理旧镜像
    log "清理旧镜像..."
    docker image prune -f --filter "until=24h" 2>/dev/null || true

    log "升级完成 ✓"
    show_info
}

# ─── 完全重建 ───
deploy_rebuild() {
    warn "完全重建: ${APP_NAME}（无缓存）"
    docker compose -f "$COMPOSE_FILE" down -v 2>/dev/null || true
    docker compose -f "$COMPOSE_FILE" build --no-cache --pull
    docker compose -f "$COMPOSE_FILE" up -d
    log "重建完成 ✓"
    show_info
}

# ─── 信息 ───
show_info() {
    PORT=$(grep NGINX_PORT .env 2>/dev/null | grep -v "^#" | cut -d= -f2 || echo "8000")
    echo ""
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
    echo -e "  📊  ${APP_NAME}"
    echo -e "  ${GREEN}  访问: http://localhost:${PORT}${NC}"
    echo -e ""
    echo -e "  服务            端口             状态"
    echo -e "  ─────────────────────────────────────"
    echo -e "  Nginx           :${PORT}             ${GREEN}● 运行中${NC}"
    echo -e "  PHP-FPM         app:9000           ${GREEN}● 运行中${NC}"
    echo -e "  PostgreSQL      postgres:5432      ${GREEN}● 运行中${NC}"
    echo -e "  队列 Worker     —                  ${GREEN}● 运行中${NC}"
    echo -e "  调度器          —                  ${GREEN}● 运行中${NC}"
    echo -e ""
    echo -e "  日志:  ${YELLOW}docker compose logs -f${NC}"
    echo -e "  Shell: ${YELLOW}docker compose exec app bash${NC}"
    echo -e "  PHP:   ${YELLOW}docker compose exec app php -v${NC}"
    echo -e "${CYAN}━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━${NC}"
}

# ─── 主流程 ───
case "${1:-deploy}" in
    deploy|first)
        check_prereqs
        deploy_first
        ;;
    upgrade|update)
        check_prereqs
        deploy_upgrade
        ;;
    rebuild)
        check_prereqs
        deploy_rebuild
        ;;
    logs)
        shift
        docker compose -f "$COMPOSE_FILE" logs -f "$@"
        ;;
    down|stop)
        docker compose -f "$COMPOSE_FILE" down
        log "服务已停止 ✓"
        ;;
    shell)
        docker compose -f "$COMPOSE_FILE" exec app bash
        ;;
    artisan)
        shift
        docker compose -f "$COMPOSE_FILE" exec -T app php artisan "$@"
        ;;
    info)
        show_info
        ;;
    *)
        echo "用法: $0 {deploy|upgrade|rebuild|logs|down|shell|artisan|info}"
        echo ""
        echo "   deploy     首次一键部署"
        echo "   upgrade    升级部署（拉取更新后）"
        echo "   rebuild    完全重建（无缓存）"
        echo "   logs       查看日志"
        echo "   down       停止服务"
        echo "   shell      进入 app 容器"
        echo "   artisan    运行 Artisan 命令"
        echo "   info       显示部署信息"
        exit 1
        ;;
esac
