# 📌 v29 Wakeup Snapshot — 2026-09-03 用户面板语言切换入口

> **当前最新进度**（v29 完成 🎉）
> **会话类型**：v29 Settings.vue 语言卡 + LanguageModal 全局挂载 + 持久化
> **状态**：✅ 5 source + 3 locales 改动 + 部署 + e2e 三语言 + commit + push 完成
> **完整 v29 细节**: MEMORY `vp92-v29-language-switcher.md`

---

## ⚡ 一句话状态总结

```
v29 状态（2026-09-03）：
✅ LanguageModal 孤儿组件 → DashboardLayout 全局挂载（仅 auth 范围）
✅ Settings.vue 新增「语言」卡片（grid-2col 后 + 订阅管理前）
✅ appStore.setLocale() 加 saveLocale() 调用 → 刷新保持 locale
✅ LanguageModal 语言列表 5→3（删 ja-JP/ko-KR/de-DE 与 locales 不一致）
✅ 3 locales 加 settings.language.{title,current,switch}（zh-CN/zh-TW/en-US 各 3 key）
✅ vite build 1.48s + index-Cpv6i3cD→Bg0NCkCU / index-DS761ScJ→D6abmHlS
✅ 部署：tar + scp + docker cp + blade 双路径覆盖 + view:clear/cache:clear/octane:reload
✅ curl 验证：新 hash 200 OK + 旧 hash 404（彻底清理）
✅ e2e 三语言切换 + 持久化全过（Sidebar 切繁简 + F5 保持 + localStorage.fe_locale=en-US）
✅ commit f1e4a66 入库 + push my master (b166b2d..f1e4a66)
✅ memory vp92-v29-language-switcher.md + MEMORY.md 索引入库
```

---

## 📁 文件改动

### 入库（5 文件 — 4 new + 1 modified）
- `src/views/account/Settings.vue`（new track，A mode）
- `src/components/layout/DashboardLayout.vue`（new track，mount LanguageModal）
- `src/components/common/LanguageModal.vue`（new track，从孤儿变 active）
- `src/stores/app.ts`（new track，setLocale 加 saveLocale）
- `theme/dashboard.blade.php`（modified，hash 同步）

### 不入库（边界维持）
- `src/locales/{zh-CN,zh-TW,en-US}.json` — 各补 3 个新 key（v25 铁律）
- 其他 src/* untracked 文件（vp92-frontend git 边界现状仅 2 tracked）

### vp92-frontend git 边界现状
仅 `theme/dashboard.blade.php` 修改 + 4 source 文件首次入库。CheckoutDialog.vue / AppSidebar.vue 是早期 tracked，其他全部 untracked（locales 边界 + 其他 source 长期 untracked）。

---

## 🚨 v29 关键修复

### 修复 1：LanguageModal 孤儿组件
- **问题**：组件写好但全代码库零 mount，零触发按钮
- **根因**：开发时挂载逻辑写漏
- **修复**：DashboardLayout 全局 mount + Settings.vue 新增卡片触发
- **教训**：写完组件必查 `grep '<ComponentName' src/` 验证 mount

### 修复 2：appStore.setLocale 不持久化
- **问题**：只更新 ref，刷新即重置
- **根因**：setLocale 缺 saveLocale 调用
- **修复**：store action 加 saveLocale 调用，store 变 locale 持久化单点入口
- **教训**：store action 应是副作用唯一入口，不应让调用方各自处理持久化

### 修复 3：LanguageModal 5→3 语言
- **问题**：提供 5 语言但 locales 只支持 3，点 ja-JP/ko-KR/de-DE 不切任何东西
- **根因**：LanguageModal 与 locales 维护不同步
- **修复**：删除 ja-JP/ko-KR/de-DE，加入缺失的 zh-TW

---

## 🚪 下次会话唤醒

```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。
里面包含了当前状态（v29 完成，用户面板语言切换入口已上线）、
待处理事项（v30 候选）和精确技术细节。
按 v30 候选决定下一步。
```

**最新 commit**: `f1e4a66 feat(i18n): Settings 页面新增语言切换入口 + LanguageModal 全局挂载 v29`
**当前 master HEAD**: `f1e4a66` ✅
**远端同步**: ✅ 已 push my master（b166b2d..f1e4a66）
**重置命令（如再卡）**:
```bash
git config --global --unset https.proxy
cd E:/jiedian/vp92-frontend && git -c http.sslBackend=openssl push my master
```

**Wakeup 完。** 下次会话直接读这个文件就能恢复上下文。

---

## 📜 v22→v29 演化路径

| 版本 | commit | 类型 | 范围 | 详情 |
|------|--------|------|------|------|
| v22 | `4ffebd7` | feature | 邀请码跨页面保持 | MEMORY `invite-code-cross-page-persistence.md` |
| v23 | `50ed411` | fix (i18n) | RegisterPage 邀请码 placeholder | MEMORY `vp92-v23-invite-placeholder-i18n.md` |
| v24 | `3b03970` | feat (i18n) | 3 auth pages + zh-TW locale | MEMORY `vp92-v24-auth-i18n-full.md` |
| v25 | `0f639df` | docs | blade overlayfs 铁律 (public/) + locales 边界 | MEMORY `vp92-v25-blade-overlayfs-locales-boundary.md` |
| v26 | `a9cb919` | feat (i18n) | AppSidebar 完整 i18n + overlayfs 铁律扩展 (theme/) | MEMORY `vp92-v26-sidebar-i18n.md` |
| v27 | `b2762dc` | docs | CLAUDE.md theme/ overlayfs 段（v26 踩坑沉淀）| MEMORY `vp92-v27-claude-md-theme-overlayfs.md` |
| v28 | `d78193a` | feat (i18n) | PlanPurchase/CheckoutDialog/Orders 三页完整 i18n | MEMORY `vp92-v28-commerce-pages-i18n.md` |
| v29 | `f1e4a66` | feat (i18n) | Settings 语言卡 + LanguageModal 全局挂载 + setLocale 持久化 | MEMORY `vp92-v29-language-switcher.md` |

---

## 🔗 关联引用

- **v29 Commit**: `f1e4a66 feat(i18n): Settings 页面新增语言切换入口 + LanguageModal 全局挂载 v29`
- **v29 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v29-language-switcher.md`
- **v28 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v28-commerce-pages-i18n.md`
- **Memory 索引**: `~/.claude/projects/E--jiedian/memory/MEMORY.md`
- **CLAUDE.md 铁令**: 「代码-文档双轨制」「git 备份常规」「严谨 + 拒绝敷衍」「不抢跑」
- **Windows git push**: 需 `-c http.sslBackend=openssl` + **必须先 unset https.proxy**
- **部署流程**: docker cp + docker exec cp blade（v26/v27 铁律）

---

## 🎯 v30 候选

### 候选 1：`<title>` Laravel 服务端 i18n
- **当前**：`dashboard.blade.php line 9/23/30` 写死 `'VP92极速加速器'`
- **方案**：服务端 `__()` 渲染（最小侵入，Vue i18n 不动）
- **风险**：中（需验证 locale 切换时 title 跟随，且 controller 传 locale）

### 候选 2：Settings.vue 现有 30+ 处硬编码中文化
- **当前**：「个人信息」「邀请好友」「重置订阅链接」等都是硬编码
- **方案**：settings.* key 已存在（en-US.json lines 487-539），统一 t()-化
- **风险**：低

### 候选 3：LanguageModal.vue 自身硬编码清理
- **当前**：「SELECT REGION」标题、「或」文案、5 个语言 label 都硬编码
- **方案**：modal header/subtitle 走 t()，语言 label 也走 t()
- **风险**：低

### 候选 4：locales key 拆分（auth.json / commerce.json / common.json）
- **当前**：locales 已 ~150+ key
- **方案**：拆分按 domain 加载
- **风险**：中（破坏所有 t() 调用）

---

## ⚠️ 已知遗留事项

| 项 | 状态 | 影响 | 下次处理 |
|---|------|------|---------|
| Settings.vue 其他硬编码中文 | ⏸️ 待 v30 | 仅 language 卡 t()-化 | v30 候选 #2 |
| LanguageModal 自身硬编码 | ⏸️ 待 v30 | 用户面看不到，但 modal title 永远是「SELECT REGION」 | v30 候选 #3 |
| `<title>` i18n | ⏸️ 待 v30 | 浏览器 tab + SEO | v30 候选 #1 |
| locales key 拆分 | ⏸️ 待 v30+ | 单文件 600+ 行 | v30 候选 #4 |
| push 网络 | ⏸️ 持续不稳 | 已 unset https.proxy | 监控 |

---

## 🧪 验证

- ✅ vite build: 1.48s, vue-vendor-CohP-jTm.js + index-Bg0NCkCU.js + index-D6abmHlS.css
- ✅ curl -sI: 新 hash 200 OK + 旧 hash 404（彻底清理）
- ✅ e2e zh-CN: 「语言 / 当前语言: 简体中文」
- ✅ e2e en-US: 「Language / Current Language: English」+ Sidebar 全英文
- ✅ e2e zh-TW: 「**語言** / **當前語言: 繁體中文**」（繁简完全分流）
- ✅ localStorage.fe_locale = 'en-US' 持久化 ✅
- ✅ F5 刷新保持 locale ✅
- ✅ git diff 校验: 5 files changed (4 new + 1 modified), 660 insertions(+), 2 deletions(-)
- ✅ push my master (b166b2d..f1e4a66) ✅