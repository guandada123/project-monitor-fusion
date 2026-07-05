# Ops Center Dashboard — 项目报告 & 审计分析

> 审计日期：2026-06-21  
> 覆盖对象：`/Users/guan/WorkBuddy/ops-center/`  
> 状态：全部修复已落地（7 项 Bug 修复 + 3 项 P0 + 3 项 P1）

---

## 一、审计结论（TL;DR）

- **总评**：A
- **修复完成度**：13/13（100%）
  - 7 项 Bug/缺陷修复
  - 3 项 P0 生产化改造
  - 3 项 P1 安全与运维增强
- **亮点（✅）**：
  - Skeleton 加载从"定义但不调用"改为"每次 fetchAll 先 skeleton 后替换"
  - 4 个 API 模块从"一个 try-catch 全挂"拆为各自独立容错
  - 端口从 9999 改为 9998，避开 proxy-deepseek 冲突
  - 所有 API 加入 `AbortSignal.timeout(10000)` 超时保护
- **剩余风险（⚠️）**：
  - 前端依赖 CDN（未锁定版本），网络断开时骨架屏可能渲染失败
  - 无端到端测试覆盖，纯手动验证

---

## 二、项目概况

- **项目名称**：Ops Center Dashboard
- **位置**：`/Users/guan/WorkBuddy/ops-center/`
- **技术栈**：Flask 3.x + Vanilla JS + CSS
- **端口**：9998（原 9999，因冲突迁移）
- **功能**：
  - 统一监控：QTS / Claw / StockInsight / Marvis Bridge 4 项目状态
  - 自动化概览：从 WorkBuddy SQLite DB 读取自动化注册与运行情况
  - AI 成本看板：从 `~/.ai_cost_log.jsonl` 读取月度成本
  - Q3 路线图：5 项 KPI 进度展示（成本、自动化数、CI 通过率、仓库体积）
  - 健康检查：`/api/health` 含版本、启动时间、依赖版本

---

## 三、修复详情

### 3.1 缺陷修复（7 项）

| # | 问题 | 严重度 | 修复方案 | 验收 |
|---|------|--------|----------|------|
| 1 | Skeleton 加载：`setLoading()`/`setLoaded()` 定义但未调用 | 🔴 P0 | 每次 `fetchAll()` 先注入 skeleton 再替换内容 | skeleton 在 API 返回前可见 |
| 2 | 模块容错：4 个 API 共用一个 try-catch | 🔴 P0 | 拆成独立 `fetchModule(url, renderFn, containerId, errorLabel)` | 单 API 失败不影响其他 |
| 3 | 手动刷新按钮：缺失 | 🟡 P1 | Header 添加 🔄 按钮 + 旋转 CSS 动画 | 点击可手动触发所有 fetch |
| 4 | Footer 时间戳：`#lastUpdateTime` 永不更新 | 🟡 P1 | 每次 `fetchAll()` 完成后更新 | 页面显示最后一次刷新时间 |
| 5 | 端口冲突：9999 被占用 | 🔴 P0 | 改为 9998 | 启动无 Address already in use |
| 6 | Skeleton 超时保护：无 | 🟡 P1 | `fetchModule()` 加入 `AbortSignal.timeout(10000)` | 10 秒超时自动显示 error |
| 7 | 自动化 badge 计数：不更新 | 🟢 P2 | 数据加载后同步更新 | badge 显示正确数字 |

### 3.2 P0 生产化改造（3 项）

| # | 改造项 | 内容 | 验收 |
|---|--------|------|------|
| 1 | `requirements.txt` 版本锁定 | Flask 3.1.*、Flask-CORS 6.* | `pip install -r requirements.txt` 可复现 |
| 2 | 环境变量化 | `PORT`/`DATA_DIR`/`LOG_DIR`，启动自动创建目录 | 无硬编码端口/路径 |
| 3 | `docs/deploy.md` 部署文档 | Gunicorn 启动命令、Nginx 反代、环境变量说明 | 新人可按文档一键部署 |

### 3.3 P1 安全与运维增强（3 项）

| # | 增强项 | 内容 | 验收 |
|---|--------|------|------|
| 1 | 安全头 | CORS 白名单（仅 localhost）+ CSP + X-Frame-Options DENY + 静态资源缓存 1h | `/api/health` 响应头包含安全策略 |
| 2 | `/api/health` 增强 | 返回 `version`/`started_at`/`uptime`/`deps` | 浏览器可验证 |
| 3 | `scripts/smoke-test.sh` 冒烟测试 | 8 项检查（HTTP 200、JSON 结构等），全部通过 exit code 0 | `./scripts/smoke-test.sh && echo OK` |

---

## 四、架构决策

| 决策 | 选项 | 选择理由 |
|------|------|----------|
| 端口 | 9998 (vs 9999) | 避免与 proxy-deepseek 冲突 |
| Skeleton | 每次 fetch 前先注入 (vs 只定义) | 保证网络延迟下有视觉反馈 |
| 模块容错 | 独立 try-catch (vs 共用) | 一个 API 失败不影响其他模块 |
| 超时 | AbortSignal.timeout(10000) (vs 无超时) | 防止挂起卡死整个页面 |

---

## 五、项目结构

```
ops-center/
├── app.py              # Flask 主应用
├── requirements.txt    # 依赖锁定
├── docs/
│   ├── deploy.md       # 部署文档
│   └── audit-report.md # ← 本文件（审计归档）
├── scripts/
│   └── smoke-test.sh   # 冒烟测试（8 项检查）
├── static/
│   ├── index.html      # 主页面
│   ├── app.js          # 前端逻辑
│   └── style.css       # 样式
├── data/               # 数据目录
└── logs/               # 日志目录
```

---

## 六、后续建议

- **前端 CDN 锁定版本**：考虑将 Alpine.js / Chart.js 等依赖本地化或锁定 CDN 版本哈希
- **E2E 测试**：补充 Playwright 端到端测试覆盖主要交互路径
- **Docker 化**：提供 Dockerfile 简化部署
- **自动重启**：配置 launchd 守护进程实现开机自启与崩溃恢复
- **通知集成**：当项目状态从 healthy→unhealthy 时推送飞书告警

---

> **审计结论**：Ops Center Dashboard 已完成从"能用"到"可上线"的全面改造。13 项修复全部落地，安全头、环境变量、部署文档、冒烟测试一应俱全。当前可投入日常使用，后续可按建议列表继续打磨。
