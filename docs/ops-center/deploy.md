# Ops Center — 生产运行指引

本项目是一个基于 Flask 的轻量级监控后台，默认开发端口为 9998。本文档提供最简生产运行方式（Gunicorn）与 Nginx 反向代理示例。

## 1. 环境准备

- Python 3.10+
- 建议在虚拟环境中安装依赖：

```bash
python3 -m venv .venv
source .venv/bin/activate
pip install -r requirements.txt
```

## 2. 环境变量

- `PORT`（可选，默认 9998）
- `DATA_DIR`（可选，默认 `./data`）
- `LOG_DIR`（可选，默认 `./logs`）

示例：

```bash
export PORT=8080
export DATA_DIR=/var/lib/ops-center/data
export LOG_DIR=/var/log/ops-center
```

## 3. 本地生产（Gunicorn）

使用 Gunicorn 以多进程方式运行 Flask 内置应用（app 变量）。

```bash
pip install gunicorn
# 绑定 0.0.0.0:PORT，2 个 worker，超时 30s
PORT=${PORT:-9998} \
  gunicorn -b 0.0.0.0:${PORT} -w 2 -t 30 \
  'app:app'
```

注意：本项目无后台常驻命令，严禁在命令后追加 `&`。

## 4. Nginx 反向代理（示例）

```
server {
    listen 80;
    server_name _;

    location / {
        proxy_pass http://127.0.0.1:9998;
        proxy_set_header Host $host;
        proxy_set_header X-Real-IP $remote_addr;
        proxy_set_header X-Forwarded-For $proxy_add_x_forwarded_for;
        proxy_set_header X-Forwarded-Proto $scheme;
    }
}
```

## 5. 健康检查

- `GET /api/health` → `{ "status": "healthy", "service": "ops-center" }`

## 6. 截图验收（可选）

若配有 Playwright 脚本，可使用：

```bash
./qa-playwright-capture.sh http://localhost:${PORT:-9998} public/qa-screenshots
```
