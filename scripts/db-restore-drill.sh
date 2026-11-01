#!/usr/bin/env bash
# pmf 恢复演练（每月 1 号，launchd com.user.pmf-db-drill）
#
# 为什么需要它：
#   2026-10-31 首次演练就抓到 role_user 外键断链 —— 备份做得好好的，
#   真恢复时却会因为依赖被排除的 users 表而失败。
#   表结构会变、依赖会变，**今天能恢复不代表下个月能**。
#   所以「能恢复」必须是被定期证伪的事实，不是一次性结论。
#
# 纪律：
#   · 只读比对：对生产库只做 count(*)，绝不写
#   · 用独立库名，且 trap 保证无论如何都清理（失败也不留垃圾）
#   · -v ON_ERROR_STOP=1 + 检查输出 ERROR —— 退出码 0 不等于导入成功
#   · 任一行数不符即非零退出（launchd 会把它记为失败）
set -euo pipefail

REPO="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
DRILL="pmf_restore_drill"
cd "$REPO"

DB_USER="project_monitor"
DB_NAME=$(grep -E '^DB_DATABASE=' .env | head -1 | cut -d= -f2-)
DB_PW=$(docker compose exec -T postgres printenv POSTGRES_PASSWORD 2>/dev/null | tr -d '\r')

[ -s "$REPO/db/schema.sql" ] || { echo "❌ 缺 db/schema.sql（先跑 db-snapshot.sh）" >&2; exit 1; }
[ -s "$REPO/db/data.sql" ]   || { echo "❌ 缺 db/data.sql（先跑 db-snapshot.sh）" >&2; exit 1; }

pg_run() { docker compose exec -T -e PGPASSWORD="$DB_PW" postgres "$@"; }
psql_q() { pg_run psql -h 127.0.0.1 -U "$DB_USER" -d "$1" -tAc "$2" | tr -d '\r'; }

cleanup() {
    pg_run psql -h 127.0.0.1 -U "$DB_USER" -d postgres -q \
        -c "DROP DATABASE IF EXISTS $DRILL" >/dev/null 2>&1 || true
}
trap cleanup EXIT

# 1. 建空库
pg_run psql -h 127.0.0.1 -U "$DB_USER" -d postgres -q \
    -c "DROP DATABASE IF EXISTS $DRILL" -c "CREATE DATABASE $DRILL" >/dev/null

# 2. 导入（任一失败立即停，并打印末尾几行便于定位）
for f in schema data; do
    if ! pg_run psql -h 127.0.0.1 -U "$DB_USER" -d "$DRILL" -v ON_ERROR_STOP=1 -q \
            < "$REPO/db/$f.sql" > /tmp/pmf-drill.log 2>&1; then
        echo "❌ $f.sql 导入失败：" >&2
        tail -5 /tmp/pmf-drill.log >&2
        exit 1
    fi
    if grep -q '^ERROR' /tmp/pmf-drill.log; then
        echo "❌ $f.sql 导入过程中出现 ERROR（退出码却是 0）：" >&2
        grep '^ERROR' /tmp/pmf-drill.log | head -3 >&2
        exit 1
    fi
done

# 3. 敏感表备份（users / role_user / tokens / integrations）
#   2026-11-01 补：原先只演练 data.sql 覆盖的 15 张表，而 data.sql **故意排除**了
#   users 与 role_user —— 恰好是 10-31 事故里真丢、真需要的那两张。
#   等于"演练了不带保险丝的电路"。这里把本地敏感快照也导进来，一起验。
SENS_FILE=$(ls -t "$HOME"/.pmf-backups/sensitive-*.sql 2>/dev/null | head -1 || true)
if [ -z "${SENS_FILE:-}" ]; then
    echo "❌ 找不到敏感表快照（$HOME/.pmf-backups/sensitive-*.sql）—— 备份不完整，无法演练完整恢复" >&2
    exit 1
fi

if ! pg_run psql -h 127.0.0.1 -U "$DB_USER" -d "$DRILL" -v ON_ERROR_STOP=1 -q \
        < "$SENS_FILE" > /tmp/pmf-drill-sens.log 2>&1; then
    echo "❌ 敏感快照导入失败：$(basename "$SENS_FILE")" >&2
    tail -5 /tmp/pmf-drill-sens.log >&2
    exit 1
fi
if grep -q '^ERROR' /tmp/pmf-drill-sens.log; then
    echo "❌ 敏感快照导入过程中出现 ERROR（退出码却是 0）：" >&2
    grep '^ERROR' /tmp/pmf-drill-sens.log | head -3 >&2
    exit 1
fi

# 4. 逐表比对行数（data.sql 的表 + 敏感快照的表）
TABLES=$( { grep -oE '^COPY public\.[a-z_]+' "$REPO/db/data.sql" | sed 's|COPY public\.||';
            grep -oE '^COPY public\.[a-z_]+' "$SENS_FILE"       | sed 's|COPY public\.||'; } | sort -u )

fail=0
n=0
for t in $TABLES; do
    a=$(psql_q "$DRILL"  "select count(*) from $t")
    b=$(psql_q "$DB_NAME" "select count(*) from $t")
    n=$((n + 1))
    if [ "$a" != "$b" ]; then
        echo "❌ $t 行数不符：恢复=$a 生产=$b" >&2
        fail=1
    fi
done

[ "$fail" = "0" ] || { echo "❌ 恢复演练未通过" >&2; exit 1; }
echo "✅ 恢复演练通过：$n 张表行数与生产一致（含敏感表 $(basename "$SENS_FILE")）· $(date '+%Y-%m-%d %H:%M')"
