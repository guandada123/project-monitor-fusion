# 「npm audit 报 N 个 high」≠「升级能修」（2026-11-18）

## 症状
PMF CI 红，`Security Scan → npm audit` 报 **6 high**；Dependabot 恰好提了 `vite 6.4.5 → 7.3.8` 的 PR，
看起来像「升级就能修」。据此差点执行 vite 7 大版本升级。

## 我犯的两个错（登记防复发）
1. **把「有升级 PR」当成「升级可修」**。没查上游到底有没有补丁，也没查升级后是否仍拉同一条漏洞链。
2. **报「CI 红 16h」时只看了 `gh run list --limit 1`**，没核对那次 run 的 `headSha` 是否为当前 HEAD。
   实际修复提交 `eaec614` 在 19:01:06 落地，其 CI run 19:01:17 **success**——我报的是修复前的旧结果。

## 正确排查顺序（下次照这个走）
1. **先对齐 SHA**：`gh run list --json headSha` 与 `git rev-parse origin/main` 比对；不一致说明结论已过期。
2. **再看 fixAvailable**：`npm audit --json` 里 `fixAvailable` 常给出**降级**建议（本次对 laravel-vite-plugin
   建议降到 0.2.4），不可信，只能当线索。
3. **验证升级是否真能消除**：`npm view <目标版本> dependencies`，看是否仍拉同一漏洞包。
4. **查上游有无补丁**：`npm view <victim> version`，再 `npm view '<victim>>当前版本'` —— 404 即无补丁。

## 本次实测结论
- picomatch 最新 **4.0.7**，`npm view 'picomatch@>4.0.7'` → **404，无补丁**（4.0.7 本身仍受影响）。
- vite **7.3.8** 的 dependencies：`picomatch ^4.0.3` / `fdir ^6.5.0` / `tinyglobby ^0.2.15` —— **与 vite 6 同一条链**。
- laravel-vite-plugin 最新 **3.2.1** 要求 **vite ^8**，升到 7 反而两边都不兼容。
→ **任何升级都消不掉该 advisory**；`scripts/npm-audit-gate.mjs` 的精确豁免（带移除条件）才是正解。

## ★升级候选（跨项目）
- **报「CI 红」前必须先核对 run.headSha 是否等于当前 HEAD**，否则会把「已修复」报成「正在红」。
- **`npm audit` 的 high 数量不是可修复性的证据**：先看 `fixAvailable`，再 `npm view '<pkg>>ver'` 确认上游有补丁。
- 常亮红灯会淹掉真警报 —— 无法上游修复的 advisory 用「精确豁免 + 移除条件 + 日志可见」处理，
  好过一票否决把 CI 永久钉红。
