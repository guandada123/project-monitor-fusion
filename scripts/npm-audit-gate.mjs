#!/usr/bin/env node
/**
 * npm audit 白名单闸门。
 *
 * 背景（2026-11-18）：原 `npm audit --audit-level=high` 是一票否决——picomatch
 * 的 ReDoS（GHSA-fc5q-39xc-vjpf / CVE-2026-112366）上游标注 Patched versions:
 * None，vite 全系依赖它，任何升级都消不掉，CI 会一直红到上游发修复版。
 * 一盏常亮的红灯会淹掉真警报（"CI 红是聚合态"纪律），故改为逐条点名：
 * 豁免名单内的 advisory 打印可见；其余 >= high 的照常拦截。
 *
 * 兼容两种 npm audit --json 结构：
 * - npm 8/9：顶层 `advisories`（key 为 GHSA id）
 * - npm 10+：顶层 `vulnerabilities`（advisory 藏在 via[] 里，source 为数字 id）
 *
 * 用法：npm audit --json | node scripts/npm-audit-gate.mjs
 *       node scripts/npm-audit-gate.mjs <file.json>   （测试用：读文件而非 stdin）
 *
 * 修改 EXEMPT 的纪律：每条必须带"理由 + 移除条件 + 日期"，动它就是动安全闸门。
 */
import { readFileSync } from "node:fs";

const SEVERITY_ORDER = ["info", "low", "moderate", "high", "critical"];
const THRESHOLD = "high";

// ─── 豁免名单（每条必须带理由与移除条件）───
// GHSA id 与 npm 数字 source id 同时登记：两种 npm 版本都能命中。
const EXEMPT = new Set(["GHSA-fc5q-39xc-vjpf", "1247710"]);
const EXEMPT_REASON =
  "picomatch ReDoS (CVE-2026-112366)：上游 Patched versions: None，vite 全系连坐、升级无法消除；" +
  "仅构建期使用（vite 为 devDependency，生产容器不加载）；ReDoS 只影响可用性、不涉及数据泄露；" +
  "EPSS 0.335%。移除条件：picomatch 发布 >4.0.7 修复版，届时 npm audit fix 后删除本条。2026-11-18";

const input = process.argv[2]
  ? readFileSync(process.argv[2], "utf8")
  : readFileSync(0, "utf8");
let data;
try {
  data = JSON.parse(input);
} catch (e) {
  console.error("❌ npm audit 输出解析失败（闸门按失败处理，不静默放行）:", e.message);
  process.exit(1);
}

// 兼容两种结构，合并为统一 advisory 列表（按 id 去重）
function collectAdvisories(data) {
  const seen = new Map();
  // npm 8/9：advisories { GHSA-id: {...} }
  for (const [ghsaId, a] of Object.entries(data.advisories ?? {})) {
    seen.set(ghsaId, {
      name: a.module_name ?? "?",
      severity: a.severity ?? "unknown",
      title: a.title ?? "",
      url: a.url ?? "",
    });
  }
  // npm 10+：vulnerabilities { pkg: { via: [对象|字符串] } }
  for (const [pkg, v] of Object.entries(data.vulnerabilities ?? {})) {
    for (const item of v.via ?? []) {
      if (typeof item !== "object") continue; // 字符串 = 传递引用（指向别的包），已有同名对象覆盖
      const urlId = String(item.url ?? "").split("/").pop();
      const id = String(item.source ?? (urlId.startsWith("GHSA-") ? urlId : "?"));
      if (!seen.has(id)) {
        seen.set(id, {
          name: item.name ?? pkg,
          severity: item.severity ?? "unknown",
          title: item.title ?? "",
          url: item.url ?? "",
        });
      }
    }
  }
  return [...seen.entries()].map(([id, a]) => ({ id, ...a }));
}

const advisories = collectAdvisories(data);
if (advisories.length === 0) {
  // 2026-11-19: 区分「真零漏洞」与「结构异常」——前者是合法状态（2026-10-24 起已修到 0 漏洞）。
  // 判据：两种已知结构的键至少其一存在且为对象；都缺 = npm 输出形态不可识别，保留 fail-safe。
  const knownStructure =
    (data.vulnerabilities && typeof data.vulnerabilities === "object") ||
    (data.advisories && typeof data.advisories === "object");
  if (knownStructure) {
    console.log(`✅ npm audit 真零漏洞（vulnerabilities/advisories 结构可解析且为空）——闸门通过`);
    process.exit(0);
  }
  console.error("⚠️ 未能从 audit 报告解析出任何 advisory（npm 结构又变了？）——按失败处理，不静默放行");
  process.exit(1);
}

const blocked = (sev) =>
  SEVERITY_ORDER.indexOf(sev) >= SEVERITY_ORDER.indexOf(THRESHOLD);

const exempted = [];
const violations = [];
for (const a of advisories) {
  if (EXEMPT.has(a.id)) {
    exempted.push(a);
    continue;
  }
  if (blocked(a.severity)) {
    violations.push(a);
  }
}

for (const e of exempted) {
  console.log(`👁️ 已豁免 ${e.id}（${e.name}，${e.severity}）—— ${EXEMPT_REASON.slice(0, 72)}…`);
}

if (violations.length > 0) {
  console.error(`\n❌ npm audit 闸门：${violations.length} 条 >= ${THRESHOLD} 且不在豁免名单：`);
  for (const v of violations) {
    console.error(`   ${v.id} ${v.name} [${v.severity}] ${v.title} ${v.url}`);
  }
  process.exit(1);
}

console.log(`\n✅ npm audit 闸门通过（豁免 ${exempted.length} 条 / 拦截 0 条，阈值 >= ${THRESHOLD}）`);
