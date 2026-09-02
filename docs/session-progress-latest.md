# 📌 v28 Wakeup Snapshot — 2026-09-03 商业三页完整 i18n

> **当前最新进度**（v28 完成 🎉）
> **会话类型**：v28 commerce pages i18n
> **状态**：✅ PlanPurchase/CheckoutDialog/Orders 三页完整 i18n + vite build + 部署 + e2e + memory 入库
> **完整 v28 细节**: MEMORY `vp92-v28-commerce-pages-i18n.md`

---

## ⚡ 一句话状态总结

```
v28 状态（2026-09-03）：
✅ PlanPurchase.vue 完整 i18n（20+ 处硬编码 t() 化 + 新 plans.badge.unknown + plans.periods 9 key）
✅ CheckoutDialog.vue 完整 i18n（template 18 处 + script countdown + 2 个新 key）
✅ Orders.vue 整体 Write 重写（useI18n + getDateLocale + 7 列头 + 5 status + 9 period + 4 toast）
✅ 3 locales 补 9 个新 key (payment.planLabel/checkout.timeoutRetry/orders.currency × 3 语言)
✅ vite build 1.52s ✅
✅ 部署（docker cp + docker exec cp） + curl 200 ✅
✅ e2e 验证：zh-CN/zh-TW/en-US 三语言 PlanPurchase/Orders/CheckoutDialog 全过
✅ 清理 dist-new/dist3/theme/dist/ 等 11 个临时文件
✅ commit d78193a 入库（4 files changed, 333 insertions(+), 73 deletions(-)）
✅ memory vp92-v28-commerce-pages-i18n.md + MEMORY.md 索引入库
⏳ push my master 重试中（network 已重试 5 次全失败，最终 unset https.proxy 重试）
```

---

## 📁 文件改动

### 入库 (4 文件)
- `src/views/commerce/Orders.vue`（Write 重写，+128 行）
- `src/components/common/CheckoutDialog.vue`（template + script 改动）
- `src/views/commerce/PlanPurchase.vue`（已 v27 完成，v28 收尾微调）
- `theme/dashboard.blade.php`（hash: index-DxfXx_xS.js → index-Cpv6i3cD.js）

### 不入库 (边界维持)
- `src/locales/zh-CN.json` / `zh-TW.json` / `en-US.json` — 各补 3 个新 key
- `src/locales/index.ts` — 未改

---

## 🚨 v28 关键修复

### 修复 1：移除 `t(key, { interpolation })` 错配
- **问题**：CheckoutDialog 模板写 `t('checkout.timeoutRetry', { action: t('checkout.payNow') })`
- **根因**：`checkout.timeoutRetry` 字符串本身不需要插值参数
- **修复**：改为 `t('checkout.timeoutRetry')` 直接调用
- **教训**：`t(key, params)` 的 key 必须真有 `{{action}}` 占位符，否则污染 i18n namespace

### 修复 2：「套餐」label 复用 `plans.title` 语义错
- **问题**：CheckoutDialog 订单摘要的 "套餐" label 用了 `t('plans.title')`
- **根因**：`plans.title` 是页面标题 ("选择您的加速方案")，不是 "套餐" label
- **修复**：新增 `payment.planLabel: "套餐"` 专用 key
- **教训**：复用 key 前查语义，专用 label 就要专用 key

### 修复 3：zh-TW 字符串不能是简体
- **手工写繁体时习惯性用简体的坑**：orders.title "订单"→"訂單" 等

---

## 🚪 下次会话唤醒

```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。
里面包含了当前状态（v28 完成，三页完整 i18n）、
待处理事项（push 重试 + v29 候选）和精确技术细节。
按 v29 候选决定下一步。
```

**最新 commit**: `d78193a feat(i18n): PlanPurchase/CheckoutDialog/Orders 三页完整 i18n v28`
**当前 master HEAD**: `d78193a` ✅ (本地)
**远端同步**: ⏳ 待 push（v26 a9cb919 + v27 b2762dc + v28 d78193a 都在本地 ahead 3 commit）
**重试命令**: `git -c http.sslBackend=openssl push my master`（**先确认 https.proxy 已 unset**）

**Wakeup 完。** 下次会话直接读这个文件就能恢复上下文。

---

## 📜 v22→v28 演化路径

| 版本 | commit | 类型 | 范围 | 详情 |
|------|--------|------|------|------|
| v22 | `4ffebd7` | feature | 邀请码跨页面保持 | MEMORY `invite-code-cross-page-persistence.md` |
| v23 | `50ed411` | fix (i18n) | RegisterPage 邀请码 placeholder | MEMORY `vp92-v23-invite-placeholder-i18n.md` |
| v24 | `3b03970` | feat (i18n) | 3 auth pages + zh-TW locale | MEMORY `vp92-v24-auth-i18n-full.md` |
| v25 | `0f639df` | docs | blade overlayfs 铁律 (public/) + locales 边界 | MEMORY `vp92-v25-blade-overlayfs-locales-boundary.md` |
| v26 | `a9cb919` | feat (i18n) | AppSidebar 完整 i18n + overlayfs 铁律扩展 (theme/) | MEMORY `vp92-v26-sidebar-i18n.md` |
| v27 | `b2762dc` | docs | CLAUDE.md theme/ overlayfs 段（v26 踩坑沉淀）| MEMORY `vp92-v27-claude-md-theme-overlayfs.md` |
| v28 | `d78193a` | feat (i18n) | PlanPurchase/CheckoutDialog/Orders 三页完整 i18n | MEMORY `vp92-v28-commerce-pages-i18n.md` |

---

## 🔗 关联引用

- **v28 Commit**: `d78193a feat(i18n): PlanPurchase/CheckoutDialog/Orders 三页完整 i18n v28`
- **v28 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v28-commerce-pages-i18n.md`
- **v27 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v27-claude-md-theme-overlayfs.md`
- **Memory 索引**: `~/.claude/projects/E--jiedian/memory/MEMORY.md`
- **CLAUDE.md 铁令**: 「代码-文档双轨制」「git 备份常规」「严谨 + 拒绝敷衍」「不抢跑」
- **Windows git push**: 需 `-c http.sslBackend=openssl` + **必须先 unset https.proxy**

---

## 🎯 v29 候选（v28 已完成三页 i18n）

### 候选 1：`<title>` 标签 i18n（v27b 留待）
- **当前**：`dashboard.blade.php line 9/23/30` 写死 `'VP92极速加速器'` + `window.settings.title`
- **方案**：服务端 Laravel `__()` 渲染（最小侵入，Vue i18n 不动）
- **工作量**：小（dashboard.blade.php 1 文件改动 + Laravel resources/lang 加 zh-CN/en-US/zh-TW）
- **风险**：中（需验证 locale 切换时 title 跟随，且 controller 传 locale）

### 候选 2：其他零散 Vue 页面 i18n
- **当前**：可能还有 Profile / Invite / Server / Nodes / Docs 等页面有硬编码中文
- **工作量**：需要 grep 摸底
- **风险**：低

### 候选 3：locales key 规范
- **当前**：locales 增长到 ~150+ key，缺乏分组
- **方案**：拆分为 auth.json / commerce.json / common.json / nav.json
- **风险**：中（破坏 v22-v28 所有已 t() 调用，需 t('group.key') 调整）

### 候选 4：push my master 网络优化
- **当前**：持续 Recv failure 或 Failed to connect github.com:443
- **方案**：考虑 SSH over 443 / gh CLI 替代 / 用代理 fallback
- **风险**：低

---

## ⚠️ 已知遗留事项

| 项 | 状态 | 影响 | 下次处理 |
|---|------|------|---------|
| v26 push `a9cb919` | ⏳ 待 push | 远端落后 3 commit | 任意时段重试 |
| v27 push `b2762dc` | ⏳ 待 push | 远端落后 3 commit | 任意时段重试 |
| v28 push `d78193a` | ⏳ 待 push | 远端落后 3 commit | 任意时段重试 |
| `git config --global https.proxy` | ✅ 已 unset (本次会话) | push 不受影响 | 监控（设回会被静默忽略）|
| `<title>` 标签 i18n | ⏸️ 待 v29 | SEO + 浏览器 tab 体验 | v29 候选 #1 |

---

## 🧪 验证

- ✅ vite build: 1.52s, vue-vendor-CohP-jTm.js + index-Cpv6i3cD.js
- ✅ curl -sI: index-Cpv6i3cD.js 200 OK
- ✅ e2e PlanPurchase: zh-CN "选择您的加速方案" / en-US "Choose Your Plan" / zh-TW "選擇您的加速方案"
- ✅ e2e Orders: zh-CN "我的订单" / en-US "My Orders" / zh-TW "我的訂單"
- ✅ e2e CheckoutDialog: zh-CN "套餐 ¥XX.XX" / en-US "Plan $XX.XX"
- ✅ Orders period map 9 个全 t() 化（monthly/quarterly/halfYear/yearly/twoYear/threeYear/onetime/resetTraffic + aliases）
- ✅ Orders status map 5 个全 t() 化（pending/processing/cancelled/completed/credited/unknown）
- ✅ git diff 校验: 4 files changed, 333 insertions(+), 73 deletions(-)
- ⏳ push 验证未完成（network 持续失败，本次会话 unset https.proxy 后重试中）