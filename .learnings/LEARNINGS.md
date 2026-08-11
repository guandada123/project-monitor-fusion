# Learnings (project-monitor-fusion)

Corrections, insights, and knowledge gaps captured during development.

**Categories**: correction | insight | best_practice | knowledge_gap

---

## 2026-08-11 — ★升级候选 Laravel 同时间戳迁移 → 外键 flaky CI [best_practice]
- 现象：PMF CI 间歇红灯（PHPUnit Tests `migrate --seed` 偶发 `SQLSTATE[42P01]: relation "projects" does not exist`），同批 Dusk 作业却通过 → 非逻辑错，是时序不确定性。
- 根因：`create_tasks_table` 与 `create_projects_table` **共享迁移时间戳 `2026_06_14_144729`**，Linux `scandir` 返回顺序不保证字母序 → 两迁移执行顺序非确定性 → tasks 内联 `->constrained()` 外键偶发在 projects 建表前执行。
- 修复：去掉 tasks 内联 `->constrained()`（裸列），外键递延到时间戳恒最后的 `170000_add_foreign_key_indexes`；对已有库零影响（该迁移已记录不重跑，旧库保留既有外键；全新库按新顺序确定性建外键）。
- 升级为铁律候选：**禁止两个迁移共享同一时间戳且存在跨表外键依赖**；外键一律放在依赖表建好后的独立迁移中。
- 诊断铁律：先 `gh run view <id> --log` 抓 CI 日志实锤，禁止脑补根因（全局🔴铁律）。
- 关联 commit：0ec84af（main），新 CI run 31480005331 全绿验证。

