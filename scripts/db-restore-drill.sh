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

# 4. 逐表比对行数 —— **基准是快照自身，不是当前生产库**
#
# 🔴 2026-11-01 修（launchd 实跑抓到的必然假阴性）：
#    原实现拿「当前生产库」当基准，而快照是若干小时前的。实测：晚上配好飞书后
#    settings 由 0 行变 6 行、slow_queries 多 1 行 → 演练立刻报红：
#        ❌ settings 行数不符：恢复=0 生产=6
#    这个红和「备份能不能恢复」毫无关系 —— 它只是说明「快照之后有人动了数据」，
#    而这是**备份的正常形态**。基准错了，判据就永远在报假警（同「基准须库外」）。
#
#    正确判据：恢复库的行数 == dump 文件里写着要导入的行数。
#    这才是在验「导入有没有丢行」。快照 vs 生产的差异只作**信息**列出，不影响判定。
dump_rows() {  # $1=文件 $2=表名 → 该表 COPY 段的数据行数
    awk -v t="$2" '
        index($0, "COPY public." t " ") == 1 { inb = 1; next }
        /^COPY public\./                     { inb = 0 }
        /^\\\.$/                             { inb = 0 }
        inb                                  { c++ }
        END                                  { print (c ? c : 0) }
    ' "$1"
}

TABLES=$( { grep -oE '^COPY public\.[a-z_]+' "$REPO/db/data.sql" | sed 's|COPY public\.||';
            grep -oE '^COPY public\.[a-z_]+' "$SENS_FILE"       | sed 's|COPY public\.||'; } | sort -u )

fail=0
n=0
drift=""
for t in $TABLES; do
    restored=$(psql_q "$DRILL" "select count(*) from $t")
    expect_in_data=$(dump_rows "$REPO/db/data.sql" "$t")
    expect_in_sens=$(dump_rows "$SENS_FILE" "$t")
    expect=$((expect_in_data + expect_in_sens))
    n=$((n + 1))

    if [ "$restored" != "$expect" ]; then
        echo "❌ $t 导入丢行：恢复=$restored 快照应含=$expect" >&2
        fail=1
    fi

    # 快照 vs 生产：只报告，不判定（快照滞后是常态）
    live=$(psql_q "$DB_NAME" "select count(*) from $t")
    if [ "$live" != "$expect" ]; then
        drift="${drift}  $t: 快照 $expect / 生产 $live"$'\n'
    fi
done

[ "$fail" = "0" ] || { echo "❌ 恢复演练未通过" >&2; exit 1; }

if [ -n "$drift" ]; then
    echo "ℹ️ 快照滞后于生产（正常，仅提示，不影响判定）："
    printf '%s' "$drift"
fi

echo "✅ 恢复演练通过：$n 张表导入行数与快照完全一致（含敏感表 $(basename "$SENS_FILE")）· $(date '+%Y-%m-%d %H:%M')"
