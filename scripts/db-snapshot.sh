#!/usr/bin/env bash
# pmf 数据库分层快照（方案 A，2026-10-31）
#
# 背景：当天误清生产库，靠「采集快照能重建 projects」侥幸恢复，
# 但 users 表（含 remember_token）永久丢失。此后没有任何 DB 备份机制。
#
# 分层设计：
#   1. schema.sql + data.sql（非敏感）→ 进 Git：可 diff、可回滚、体积小
#   2. 敏感表（users / personal_access_tokens / integrations）→ 本地 ~/.pmf-backups/
#      **不进 Git**：remember_token 是「活凭据」，拿到即可登录且无需密码；
#      Git 历史永久保留，删不掉。
#   3. 运行时表（cache/jobs/sessions…）不备份，无恢复价值。
#
# 三条纪律：
#   · 自检不通过就**非零退出**——不许"文件生成了就算成功"
#   · 先写 .tmp 再原子 mv——中途失败不会污染上一份好快照
#   · 每次产出 last-snapshot.json，让「上次什么时候备份、多大」可审计
#
# ─── 恢复演练（2026-10-31 做过一次，务必定期重做）───────────────────
# 备份只在「能恢复」时才算备份。演练方法（不碰生产库）：
#   PW=$(docker compose exec -T postgres printenv POSTGRES_PASSWORD)
#   psql -d postgres -c "CREATE DATABASE pmf_restore_drill"
#   psql -d pmf_restore_drill -v ON_ERROR_STOP=1 < db/schema.sql
#   psql -d pmf_restore_drill -v ON_ERROR_STOP=1 < db/data.sql
#   # 逐表对比行数，然后 DROP DATABASE pmf_restore_drill
#
# ⚠️ 首次演练就抓到一个致命缺陷：role_user 曾被放进 data.sql，
#    而它依赖被排除的 users(id) → 恢复必然外键失败。已改为跟随 users 进敏感备份。
#    **不做演练，这份备份会在真出事那天才暴露不可用。**
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
OUT="$REPO/db"
SEC="$HOME/.pmf-backups"
KEEP_DAYS=7
DATE=$(date +%Y%m%d)
TS=$(date -u +%Y-%m-%dT%H:%M:%SZ)

cd "$REPO"

# 凭证：不能用 .env 的 DB_USERNAME
#   · .env 里 DB_USERNAME=guan 是**宿主系统用户名**，postgres 容器内并没有这个 role
#   · compose 的 POSTGRES_USER=project_monitor 才是超级用户
#   · 容器内默认走 unix socket → peer 认证（要求 OS 用户=DB 用户），必然失败
# 故统一用 project_monitor + -h 127.0.0.1 走 TCP。
# 密码直接问容器要（.env 的 DB_PASSWORD 是空的，compose 会回落到默认值，
# 写死默认值等于把密码抄进仓库 —— 从容器读才始终与真实值一致）。
DB_USER="project_monitor"
DB_NAME=$(grep -E '^DB_DATABASE=' .env | head -1 | cut -d= -f2-)
DB_PW=$(docker compose exec -T postgres printenv POSTGRES_PASSWORD 2>/dev/null | tr -d '\r')
[ -n "${DB_NAME:-}" ] || { echo "❌ 读不到 DB_DATABASE" >&2; exit 1; }
[ -n "${DB_PW:-}" ]   || { echo "❌ 读不到 postgres 的 POSTGRES_PASSWORD" >&2; exit 1; }

psql_q() { docker compose exec -T -e PGPASSWORD="$DB_PW" postgres psql -h 127.0.0.1 -U "$DB_USER" -d "$DB_NAME" -tAc "$1" | tr -d '\r'; }
dump()   { docker compose exec -T -e PGPASSWORD="$DB_PW" postgres pg_dump -h 127.0.0.1 -U "$DB_USER" -d "$DB_NAME" --no-owner --no-privileges "$@"; }

# pg_dump 15+ 会在首尾插入 `\restrict <随机key>` / `\unrestrict <随机key>`（防注入）。
# 这个 key 每次都随机 → 文件字节永远不同 → Git diff 全是噪音、备份看似"天天在变"。
# 备份是自己写的，不需要这层保护，直接过滤掉，让 diff 只反映真实变更。
strip_restrict() { sed -e '/^\\restrict /d' -e '/^\\unrestrict /d'; }

mkdir -p "$OUT" "$SEC"
cleanup() { rm -f "$OUT"/*.tmp "$SEC"/*.tmp 2>/dev/null || true; }
trap cleanup EXIT

# ── 1. 结构（26 张表，无数据）──
dump --schema-only | strip_restrict > "$OUT/schema.sql.tmp"

# ── 2. 非敏感数据 ──
# role_user 依赖 users(id)，若留在 data.sql 里，恢复时必然外键失败（2026-10-31 演练实证）
SENSITIVE="users role_user personal_access_tokens sessions password_reset_tokens integrations"
RUNTIME="cache cache_locks jobs job_batches failed_jobs"
EXCLUDES=()
for t in $SENSITIVE $RUNTIME; do EXCLUDES+=("--exclude-table=$t"); done
dump --data-only "${EXCLUDES[@]}" | strip_restrict > "$OUT/data.sql.tmp"

# ── 3. 敏感数据（本地，绝不进 Git）──
SENS_TABLES=()
for t in users role_user personal_access_tokens integrations; do SENS_TABLES+=("--table=$t"); done
dump --data-only "${SENS_TABLES[@]}" | strip_restrict > "$SEC/sensitive-$DATE.sql.tmp"

# ── 4. 自检 ──
fail() { echo "❌ 自检失败：$1" >&2; exit 1; }

[ -s "$OUT/schema.sql.tmp" ]        || fail "schema.sql 为空"
[ -s "$OUT/data.sql.tmp" ]          || fail "data.sql 为空"
[ -s "$SEC/sensitive-$DATE.sql.tmp" ] || fail "敏感快照为空"

DB_TABLES=$(psql_q "select count(*) from pg_tables where schemaname='public'")
DUMP_TABLES=$(grep -c '^CREATE TABLE ' "$OUT/schema.sql.tmp" || true)
[ "$DB_TABLES" = "$DUMP_TABLES" ] || fail "表数不符：库 $DB_TABLES / dump $DUMP_TABLES"

# 关键表逐行核对：从 COPY 段数行数，与库里 count(*) 比对
check_rows() {
    local t=$1 db_n dump_n
    db_n=$(psql_q "select count(*) from $t")
    dump_n=$(awk -v pat="^COPY public.$t " '
        $0 ~ pat { insec=1; next }
        /^\\\.$/  { insec=0 }
        insec    { c++ }
        END      { print (c ? c : 0) }
    ' "$OUT/data.sql.tmp")
    [ "$db_n" = "$dump_n" ] || fail "$t 行数不符：库 $db_n / dump $dump_n"
    echo "$db_n"
}
P=$(check_rows projects)
R=$(check_rows roles)

# ── 5. 原子就位 ──
mv "$OUT/schema.sql.tmp" "$OUT/schema.sql"
mv "$OUT/data.sql.tmp"   "$OUT/data.sql"
mv "$SEC/sensitive-$DATE.sql.tmp" "$SEC/sensitive-$DATE.sql"
chmod 600 "$SEC/sensitive-$DATE.sql"

# 关于末尾换行（2026-10-31 实测，踩了两次）：
#   pg_dump 的输出**以 \n\n 结尾**（不是没有、也不是一个），
#   而 .pre-commit 的 end-of-file-fixer 要求「末尾恰好一个 \n」。
#   → 既不能"补一个"（变三个），也不能不管（两个仍被拦）。
#   必须把末尾连续换行收敛成一个。用 perl -0 才能跨行匹配（macOS sed 无 \z）。
for f in "$OUT/schema.sql" "$OUT/data.sql"; do
    perl -0pi -e 's/\n+\z/\n/' "$f"
done

# ── 6. 状态文件（可审计）──
size_of() { wc -c < "$1" | tr -d ' '; }
cat > "$OUT/last-snapshot.json" <<EOF
{
  "taken_at": "$TS",
  "database": "$DB_NAME",
  "tables": $DUMP_TABLES,
  "schema_bytes": $(size_of "$OUT/schema.sql"),
  "data_bytes": $(size_of "$OUT/data.sql"),
  "sensitive_file": "sensitive-$DATE.sql",
  "rows": { "projects": $P, "roles": $R }
}
EOF

# ── 7. 本地敏感快照保留 N 天 ──
find "$SEC" -name 'sensitive-*.sql' -type f -mtime +"$KEEP_DAYS" -delete 2>/dev/null || true

echo "✅ 快照完成：${DUMP_TABLES} 张表 · schema $(size_of "$OUT/schema.sql")B · data $(size_of "$OUT/data.sql")B · projects=$P roles=$R"
