# 📦 Project Monitor — 部署指南

> **适用于**: 生产环境 Docker 部署  
> **技术栈**: PHP 8.3-FPM + Nginx + PostgreSQL 16 + Queue Worker + Scheduler

---

## 目录

1. [架构概览](#1-架构概览)
2. [前置要求](#2-前置要求)
3. [Docker 部署](#3-docker-部署)
4. [手动部署](#4-手动部署)
5. [配置说明](#5-配置说明)
6. [调度任务](#6-调度任务)
7. [日志管理](#7-日志管理)
8. [备份与恢复](#8-备份与恢复)
9. [监控与告警](#9-监控与告警)
10. [常见问题](#10-常见问题)
11. [安全加固](#11-安全加固)

---

## 1. 架构概览

```
                        ┌───────────────────────┐
                        │   客户端 (浏览器)      │
                        └───────────┬───────────┘
                                    │ :8000 (HTTP)
                                    ▼
                        ┌───────────────────────┐
                        │   Nginx (反向代理)     │
                        │  · 静态文件直接返回     │
                        │  · PHP 请求转发到 FPM  │
                        └───────────┬───────────┘
                                    │ :9000 (FastCGI)
                                    ▼
                        ┌───────────────────────┐
                        │   PHP-FPM (app)        │
                        │  · Laravel 应用逻辑    │
                        │  · Livewire 动态渲染   │
                        └───────────┬───────────┘
                                    │
              ┌─────────────────────┼─────────────────────┐
              │                     │                     │
              ▼                     ▼                     ▼
   ┌──────────────────┐  ┌──────────────────┐  ┌──────────────────┐
   │  Queue Worker     │  │  Scheduler        │  │  PostgreSQL 16   │
   │  · 异步队列处理   │  │  · 定时任务调度    │  │  · 主数据库       │
   │  · 失败重试 ×3    │  │  · 日报/指标/风险  │  │  · 持久化存储     │
   └──────────────────┘  └──────────────────┘  └──────────────────┘
```

### 容器职责

| 容器 | 镜像 | CPU | 内存 (建议) | 用户 |
|------|------|-----|------------|------|
| **nginx** | nginx:1.27-alpine | 轻量 | 64MB | `nginx` |
| **app** | project-monitor:latest | 中 | 256MB | `www-data`(非 root) |
| **postgres** | postgres:16-alpine | 中 | 512MB | `postgres` |
| **queue** | project-monitor:latest | 中 | 256MB | `www-data` |
| **scheduler** | project-monitor:latest | 低 | 128MB | `www-data` |

---

## 2. 前置要求

### 硬件建议

| 环境 | CPU | 内存 | 磁盘 |
|------|-----|------|------|
| **最低** | 2 vCPU | 2GB | 20GB |
| **推荐** | 4 vCPU | 4GB | 40GB |
| **高并发** | 8 vCPU | 8GB | 80GB (SSD) |

### 软件要求

- **Docker Engine 24+**（含 Compose V2）
- **Git**（用于拉取源码）
- **建议**: Docker BuildKit 启用

### 检查清单

```bash
# 检查 Docker
docker --version && docker compose version

# 启用 BuildKit
export DOCKER_BUILDKIT=1
export COMPOSE_DOCKER_CLI_BUILD=1
```

---

## 3. Docker 部署

### 3.1 一键部署（推荐）

```bash
# 克隆项目
git clone https://github.com/guandada123/project-monitor.git
cd project-monitor

# 首次部署
bash deploy.sh
# 或
bash deploy.sh deploy
```

部署脚本会自动：
1. 检查 Docker 环境
2. 创建 `.env` 文件
3. 启动所有容器
4. 输出访问信息

### 3.2 手动部署

```bash
# 1. 配置环境变量
cp .env.example .env
# 编辑 .env，设置 APP_KEY 和 DB_PASSWORD

# 2. 生成 APP_KEY
docker compose run --rm app php artisan key:generate --force

# 3. 构建并启动
docker compose up -d --build

# 4. 运行数据库迁移
docker compose exec app php artisan migrate --force

# 5. 初始化种子数据
docker compose exec app php artisan db:seed --force

# 6. 验证部署
docker compose ps
curl -I http://localhost:8000
```

### 3.3 升级部署

```bash
# 拉取最新代码
git pull

# 重建并重启
bash deploy.sh upgrade

# 或手动
docker compose build --pull
docker compose up -d --remove-orphans
docker image prune -f
```

### 3.4 完全重建

```bash
bash deploy.sh rebuild
# 或
docker compose down -v   # ⚠️ 会删除数据卷！
docker compose build --no-cache --pull
docker compose up -d
```

### 3.5 常用 Docker 命令

```bash
# 查看运行状态
docker compose ps

# 日志
docker compose logs -f          # 所有容器
docker compose logs -f app      # 仅应用
docker compose logs -f nginx    # 仅 Nginx

# 进入容器
docker compose exec app bash
docker compose exec postgres psql -U project_monitor -d project_monitor

# 重启服务
docker compose restart app
docker compose restart nginx

# 停止服务
docker compose down             # 停止但保留数据
docker compose down -v          # ⚠️ 停止并删除数据卷
```

---

## 4. 手动部署

如需在裸机（非 Docker）上部署，请按以下步骤操作。

### 4.1 系统依赖

```bash
# Ubuntu/Debian
sudo apt update
sudo apt install -y php8.3-cli php8.3-fpm php8.3-pgsql php8.3-bcmath \
    php8.3-zip php8.3-sockets php8.3-xml php8.3-mbstring php8.3-curl \
    php8.3-redis php8.3-intl php8.3-pcntl \
    nginx postgresql-16 composer nodejs npm

# macOS (Homebrew)
brew install php@8.3 composer node postgresql@16 nginx
```

### 4.2 应用部署

```bash
# 1. 克隆项目
git clone https://github.com/guandada123/project-monitor.git
cd project-monitor

# 2. 配置环境
cp .env.example .env
# 编辑 .env 中的数据库连接和 APP_KEY

# 3. 安装依赖
composer install --no-dev --optimize-autoloader
npm ci --production && npm run build

# 4. 生成密钥
php artisan key:generate --force

# 5. 创建存储目录
mkdir -p storage/app storage/framework/cache/data \
         storage/framework/sessions storage/framework/views \
         storage/logs bootstrap/cache
chmod -R 775 storage bootstrap/cache

# 6. 数据库初始化
php artisan migrate --force
php artisan db:seed --force

# 7. 创建符号链接
php artisan storage:link --force

# 8. 配置缓存（生产优化）
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan event:cache

# 9. 配置 Supervisor
sudo tee /etc/supervisor/conf.d/project-monitor.conf <<'EOF'
[program:project-monitor-queue]
process_name=%(program_name)s_%(process_num)02d
command=php /var/www/project-monitor/artisan queue:work --tries=3 --timeout=90 --sleep=3
autostart=true
autorestart=true
stopasgroup=true
killasgroup=true
user=www-data
numprocs=2
redirect_stderr=true
stdout_logfile=/var/www/project-monitor/storage/logs/queue-worker.log

[program:project-monitor-scheduler]
command=php /var/www/project-monitor/artisan schedule:work
autostart=true
autorestart=true
user=www-data
redirect_stderr=true
stdout_logfile=/var/www/project-monitor/storage/logs/scheduler.log
EOF

sudo supervisorctl reread && sudo supervisorctl update
```

### 4.3 Nginx 配置

```nginx
server {
    listen 80;
    server_name project-monitor.example.com;
    root /var/www/project-monitor/public;

    add_header X-Frame-Options "SAMEORIGIN";
    add_header X-Content-Type-Options "nosniff";
    add_header X-XSS-Protection "1; mode=block";

    charset utf-8;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location = /favicon.ico { access_log off; log_not_found off; }
    location = /robots.txt  { access_log off; log_not_found off; }

    error_page 404 /index.php;

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.3-fpm.sock;
        fastcgi_param SCRIPT_FILENAME $realpath_root$fastcgi_script_name;
        include fastcgi_params;
    }

    location ~ /\.(?!well-known).* {
        deny all;
    }

    # 静态资源缓存
    location ~* \.(js|css|png|jpg|jpeg|gif|ico|svg|woff2?)$ {
        expires 1y;
        add_header Cache-Control "public, immutable";
        access_log off;
    }
}
```

### 4.4 Crontab 配置

```bash
# 添加调度任务
* * * * * cd /var/www/project-monitor && php artisan schedule:run >> /dev/null 2>&1
```

---

## 5. 配置说明

### 5.1 核心环境变量

| 变量 | 必填 | Docker 默认 | 手动部署默认 | 说明 |
|------|------|-------------|-------------|------|
| `APP_KEY` | ✅ | 自动生成 | 需生成 | Laravel 应用密钥 |
| `APP_ENV` | ✅ | `production` | `production` | 必须设为 `production` |
| `APP_DEBUG` | ✅ | `false` | `false` | 生产环境必须关闭 |
| `APP_URL` | ✅ | `http://localhost:8000` | — | 替换为实际域名 |
| `DB_PASSWORD` | ✅ | `secret` | — | 生产环境需修改 |
| `SESSION_DRIVER` | — | `database` | `file` | Docker 推荐 `database` |
| `CACHE_STORE` | — | `database` | `file` | Docker 推荐 `database` |

### 5.2 飞书通知配置

在系统设置页面（或通过 `.env`）配置飞书 Webhook：

```dotenv
# .env（可选，也可通过 UI 配置）
FEISHU_WEBHOOK_URL=https://open.feishu.cn/open-apis/bot/v2/hook/your-webhook-id
```

> ⚠️ Webhook URL 在数据库中通过 AES-256-CBC 加密存储，UI 中默认脱敏显示。

### 5.3 GitHub 集成配置

通过管理后台 → 集成 → 添加 GitHub 配置：

| 字段 | 说明 |
|------|------|
| **组织** | GitHub 组织名（如 `my-org`） |
| **仓库** | 仓库名或 `org/repo` 格式 |
| **Token** | GitHub Personal Access Token（需 `repo` 权限） |

### 5.4 角色权限配置

通过管理后台 → 角色管理配置：

- **管理员**: 全部 14 项权限
- **项目经理**: 查看 + 管理项目/指标/集成/风险
- **查看者**: 只读访问

---

## 6. 调度任务

### 6.1 任务列表

| 命令 | 频率 | 描述 | 日志文件 |
|------|------|------|---------|
| `daily:report` | 每日 08:30 | 项目日报生成与推送 | `logs/daily-report.log` |
| `metrics:collect` | 每小时 | 指标快照自动采集 | `logs/metrics-collect.log` |
| `risk:scan --no-push` | 每 30 分钟 | 风险自动扫描 | `logs/risk-scan.log` |
| `github:sync` | 每 30 分钟 | GitHub 数据自动同步 | `logs/github-sync.log` |

### 6.2 手动执行

```bash
# Docker
docker compose exec app php artisan daily:report
docker compose exec app php artisan metrics:collect
docker compose exec app php artisan risk:scan
docker compose exec app php artisan github:sync

# 手动部署
php artisan daily:report
php artisan metrics:collect
php artisan risk:scan
php artisan github:sync
```

### 6.3 重试机制

`github:sync` 命令内置 3 次重试（线性退避 2s/4s）：

```
首次执行 → 失败 → 等待 2s → 重试 1 → 失败 → 等待 4s → 重试 2 → 失败 → 更新 error_message
```

---

## 7. 日志管理

### 7.1 日志文件

| 文件 | 位置 | 说明 |
|------|------|------|
| Laravel 日志 | `storage/logs/laravel.log` | 应用运行时日志 |
| 日报推送 | `storage/logs/daily-report.log` | 日报生成与推送记录 |
| 指标采集 | `storage/logs/metrics-collect.log` | 指标快照采集记录 |
| 风险扫描 | `storage/logs/risk-scan.log` | 风险检测记录 |
| GitHub 同步 | `storage/logs/github-sync.log` | 数据同步记录 |
| 队列日志 | `storage/logs/queue-worker.log` | 队列处理记录 |

### 7.2 日志轮转

建议配置 `logrotate`：

```
/var/www/project-monitor/storage/logs/*.log {
    daily
    rotate 30
    compress
    delaycompress
    missingok
    notifempty
    copytruncate
}
```

### 7.3 Docker 日志查看

```bash
# 按容器
docker compose logs -f --tail=100 app

# 按时间
docker compose logs --since="2026-06-14T10:00:00" app

# 导出
docker compose logs app > app-logs-$(date +%Y%m%d).txt
```

---

## 8. 备份与恢复

### 8.1 数据库备份

```bash
#!/bin/bash
# backup.sh — 每日备份脚本
BACKUP_DIR="/backups/project-monitor"
DATE=$(date +%Y%m%d_%H%M%S)
mkdir -p "$BACKUP_DIR"

# Docker 部署
docker compose exec -T postgres pg_dump \
    -U project_monitor project_monitor \
    | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# 手动部署
pg_dump -U project_monitor project_monitor \
    | gzip > "$BACKUP_DIR/db_$DATE.sql.gz"

# 保留最近 30 天
find "$BACKUP_DIR" -name "db_*.sql.gz" -mtime +30 -delete

echo "✅ 备份完成: db_$DATE.sql.gz"
```

### 8.2 数据恢复

```bash
# Docker 部署
gunzip -c "$BACKUP_DIR/db_20260614_083000.sql.gz" \
    | docker compose exec -T postgres psql -U project_monitor -d project_monitor

# 手动部署
gunzip -c "$BACKUP_DIR/db_20260614_083000.sql.gz" \
    | psql -U project_monitor -d project_monitor
```

### 8.3 文件备份

```bash
# 备份用户上传和导出文件
tar -czf "$BACKUP_DIR/storage_$DATE.tar.gz" \
    -C /var/www/project-monitor storage/app
```

---

## 9. 监控与告警

### 9.1 内置慢查询监控

系统内置 SQL 慢查询监控功能，访问 `/monitoring` 查看面板：

- **阈值**: ≥500ms 记录日志，≥1000ms 推送飞书告警
- **调用栈**: 自动定位业务代码文件:行号
- **可视面板**: 趋势图、按耗时/频率排序、SQL 全文展开

### 9.2 健康检查端点

Docker 部署内置健康检查：

```bash
# 应用健康检查
curl -s http://localhost:8000/health  # 需配置此路由

# 容器健康状态
docker compose ps
```

### 9.3 推荐的外部监控

| 工具 | 用途 |
|------|------|
| **Prometheus + Grafana** | 基础设施监控 |
| **Laravel Pulse** | PHP 应用性能监控 |
| **Sentry** | 错误追踪 |
| **Uptime Kuma** | 站点存活监控 |

---

## 10. 常见问题

### 10.1 部署后访问返回 502

**原因**: PHP-FPM 未启动或 Nginx 代理配置错误。

**排查**:
```bash
docker compose logs app     # 检查 PHP-FPM 错误
docker compose logs nginx   # 检查 Nginx 错误
docker compose exec app php -v  # 验证 PHP 正常运行
```

### 10.2 数据库连接失败

**原因**: 数据库未就绪或凭据错误。

**排查**:
```bash
docker compose logs postgres
docker compose exec postgres psql -U project_monitor -d project_monitor -c "SELECT 1"
```

### 10.3 Webhook 推送失败

**原因**: Webhook URL 未配置、无效或网络不可达。

**排查**:
1. 检查 Settings 页面 Webhook 状态是否显示为「已配置」
2. 点击「发送测试」验证连通性
3. 确认服务器能访问 `open.feishu.cn`

### 10.4 GitHub 同步失败

**原因**: Token 过期、权限不足或 API 限流。

**排查**:
```bash
docker compose exec app php artisan github:sync --integration-id=1
# 查看日志
docker compose logs app | grep "github:sync"
```

### 10.5 配额 / 日志警告

查看 `storage/logs/laravel.log` 中的 SlowQueryMonitor 告警：

```
Slow query detected: SELECT * FROM ... (1500.23ms)
```

如果出现大量慢查询：
1. 检查数据库索引
2. 使用 `EXPLAIN ANALYZE` 分析查询
3. 考虑添加或优化索引

---

## 11. 安全加固

### 11.1 生产环境必做

- [ ] `APP_ENV=production` 且 `APP_DEBUG=false`
- [ ] 生成强 `APP_KEY`（`php artisan key:generate --force`）
- [ ] 修改默认管理员密码
- [ ] 修改 `DB_PASSWORD` 为强密码
- [ ] 配置 HTTPS（Let's Encrypt 或反向代理）
- [ ] 启用 CSP 头（已内置在 `AppServiceProvider`）
- [ ] 配置 `SESSION_ENCRYPT=true`

### 11.2 网络安全建议

- 将 Nginx 端口映射为非标准端口（如 `8080`）
- 配置防火墙限制访问来源
- 启用 fail2ban 防止暴力破解
- 定期更新 Docker 镜像基础层

### 11.3 数据安全

- Webhook URL 等敏感配置已使用 AES-256-CBC 加密存储
- 会话数据加密存储
- 密码使用 bcrypt（rounds=12）哈希
- SQL 注入防护：所有查询使用 Eloquent ORM 或参数化查询

---

## 📝 附录

### A. Docker 镜像标签

| 标签 | 描述 | 更新策略 |
|------|------|---------|
| `latest` | 最新稳定版 | 每次部署更新 |
| `v1.0.0` | 特定版本 | 版本发布时 |

### B. 端口映射

| 服务 | 容器端口 | 宿主机默认端口 | 协议 |
|------|---------|---------------|------|
| Nginx | 80 | 8000 | TCP |
| PostgreSQL | 5432 | — | TCP（仅内部网络） |

### C. 数据卷

| 卷名 | 挂载点 | 数据 |
|------|--------|------|
| `pgdata` | `/var/lib/postgresql/data` | 数据库文件 |
| `app-storage` | `/var/www/storage` | 日志、导出文件、会话 |

---

> 文档版本: v1.1.0 · 最后更新: 2026-06-14
