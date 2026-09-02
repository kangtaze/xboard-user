# 📌 v32 Wakeup Snapshot — 2026-09-03 Dashboard.vue 主区 i18n

> **当前最新进度**（v32 完成 🎉）
> **会话类型**：v31 wakeup 候选的 "Dashboard.vue 主区 i18n" → 全 t()-化 + 32 新 key
> **状态**：✅ 3 locales 完美对齐 + vite build 1.42s + 部署 + e2e 3 语言 + commit + push
> **完整 v32 细节**: MEMORY `vp92-v32-dashboard-i18n.md`

---

## ⚡ 一句话状态总结

```
v32 状态（2026-09-03）：
✅ 摸底 Dashboard.vue (849 行): ~30 处硬编码中文 + script 4 处（formatDate/daysUntil/fallbacks/toast）
✅ 3 locales 补 32 新 dashboard.* key (perfectly aligned, missing 0, extra 0)
✅ Dashboard.vue 整页 Write 重写为 t()-化（useI18n + locale.value for toLocaleDateString）
✅ vite build 1.42s ✅ + index-DyR1DIkZ.js + index-BM7iSI3y.css + Dashboard-ZjVV5q8Q.js (vue-vendor 不变)
✅ docker cp + docker exec cp blade (overlayfs 铁律)
✅ artisan 3 条分开跑（v31 chain hang 铁律沿用）
✅ e2e zh-CN/en-US/zh-TW 全过 + 0 console error
✅ commit bd0c3a9 (852 行) + push my master (0db2236..bd0c3a9)
✅ vp92-frontend tracked 边界扩展：Dashboard.vue untracked → tracked (v32 现共 26 tracked)
✅ memory vp92-v32-dashboard-i18n.md + MEMORY.md 索引入库
```

---

## 📁 v32 文件改动

| 文件 | 类型 | 关键改动 |
|------|------|---------|
| `src/views/dashboard/Dashboard.vue` | untracked → **tracked** | 849 行整页 Write 重写（useI18n + 30+ 处 t() 化） |
| `theme/dashboard.blade.php` | modified | line 17 CSS hash: `CrfcEOxX`→`BM7iSI3y`, line 37 JS hash: `B4DW_UGC`→`DyR1DIkZ` |
| `src/locales/{zh-CN,zh-TW,en-US}.json` | untracked | 3 locales 各 +32 个 dashboard.* key（welcomeBack/planValidUntil/appDownloads.*/subBar.*/stats.*/chart.*/messages.*/fallbacks.*）|

---

## 🚨 v32 新增铁律 / 经验

### 1. Locale key 存在 ≠ 被 Vue 引用（zombie key）
v25 时期 i18n 化埋下了 dashboard.quickBar/stats/traffic 等 key，但 Dashboard.vue 完全没引用这些 key — **key 占据 locale 文件但 dist bundle 没真正使用**。

**v32 修复**：Vue 引用 + 激活现有 key + 32 新 key 覆盖原硬编码。

### 2. brand 缩写 vs 本地化名称分离
- button text "Clash"/"SingBox"/"SS"/"Trojan"/"V2Ray" 在所有 locale 保持原文（VPN 圈 brand 缩写）
- toast 消息用完整本地化名 (`t('dashboard.subBar.fmtLabels.clash')` → "Clash 订阅"/"Clash subscription"/"Clash 訂閱")

**避免 brand 名被错误翻译**，同时 toast 仍可读。

### 3. vp92-frontend tracked 边界扩展
v32 之前 25 个 tracked → v32 现 26 个（新增 Dashboard.vue）。

**原则**：源码级 SPA 组件入 tracked（与 v25 「locales 4 文件不入库」原则不冲突）。

### 4. e2e 中 pre-existing 数据问题 vs i18n 范围
"Invalid Date" + "242933367天" 是 admin@demo.com 测试用户 expired_at=0/null 导致的。**v32 不处理**（server 数据问题，非 i18n 范围）。未来 v33+ 候选：dashboard 加 fallback「无到期日」。

---

## 📊 v32 验证记录

### Locales 对齐（关键指标）
```
counts (leaf keys): zh-CN 465 / zh-TW 465 / en-US 465 (v31: 433 + 32 新增)
zh-TW vs zh-CN: missing 0, extra 0
en-US vs zh-CN: missing 0, extra 0
== 完美对齐 ==
```

### HTTP 验证
```
新 hash 200 OK ✅:
- /theme/VP92/assets/index-DyR1DIkZ.js → 200 (Content-Length: 53479)
- /theme/VP92/assets/index-BM7iSI3y.css → 200 (Content-Length: 49192)
- /theme/VP92/assets/vue-vendor-CohP-jTm.js → 200 (unchanged)
- /theme/VP92/assets/Dashboard-ZjVV5q8Q.js → 200 (NEW Dashboard.vue chunk 11.40 kB)

/dashboard 渲染新 hash ✅:
- vue-vendor-CohP-jTm (line 16) unchanged
- index-BM7iSI3y.css (line 17) ✅ NEW
- index-DyR1DIkZ.js (line 37) ✅ NEW
```

### 容器内 blade 验证
```bash
docker exec xboard sh -c 'grep -E "index-|vue-vendor" /www/theme/VP92/dashboard.blade.php'
# vue-vendor-CohP-jTm ✓
# index-BM7iSI3y ✓
# index-DyR1DIkZ ✓
```

### chrome-devtools e2e
```
zh-CN: 快速导入订阅/欢迎回来, admin@demo.com/您的 VIP1 订阅有效期至 Invalid Date/
      续费订阅/应用下载/Windows 客户端/Android 客户端/iOS 客户端/立即下载×3/
      已用流量/本月/剩余流量/总计 100.0 TB/到期时间/在线设备/无限 台/无限设备/
      流量使用/已使用/已用 0.0 GB/剩余 100.0 TB/流量统计/暂无流量记录 ✓

en-US: Quick Import Subscription/Welcome back, admin@demo.com/Your VIP1 subscription is valid until Invalid Date/
      Renew Subscription/App Downloads/Windows Client/Android Client/iOS Client/Download Now×3/
      Used Traffic/This month/Remaining Traffic/Total 100.0 TB/Expires In/Online Devices/Unlimited/Unlimited devices/
      Traffic Usage/Used/Used 0.0 GB/Remaining 100.0 TB/Traffic Statistics/No traffic records yet ✓

zh-TW: 快速匯入訂閱/歡迎回來, admin@demo.com/您的 VIP1 訂閱有效期至 Invalid Date/
      續訂訂閱/應用下載/Windows 用戶端/Android 用戶端/iOS 用戶端/立即下載×3/
      已用流量/本月/剩餘流量/總計 100.0 TB/到期時間/線上裝置/無限/無限裝置/
      流量使用/已使用/已用 0.0 GB/剩餘 100.0 TB/流量統計/暫無流量記錄 ✓

console.error: 0 ✓
```

---

## 🚧 v33 候选（next session 待决定）

- **`<title>` i18n** — v27b 已决策留作 v28+ → 现在 v33。dashboard.blade.php line 9 仍是硬编码 "VP92极速加速器"。可用服务端 Laravel `__()` 渲染
- **`theme/tutorial.blade.php` i18n wire-up** — tutorial 页英文用户看中文，50+ 字符串加 data-i18n 属性 + JS 读 localStorage.fe_locale + locales JSON 动态渲染
- **Dashboard "Invalid Date" / "242933367天" 数据 fallback** — dashboard.vue 对 expired_at=0/null 加友好显示「无到期日」/「永久」
- **t(key, { interpolation }) 占位符审计** — v32 教训延伸：v30 Settings.vue 30+ 处 t()-化中是否有 timeoutRetry 类陷阱
- **LanguageModal 入口去留** — v30 撤回 v29 Settings 卡，但 DashboardLayout 全局挂载仍保留。未来加「高级设置」入口时可继续使用
- **`/invite` 页面** — Settings.vue 引入 `router-link to="/invite"` 但用户没访问过，待 e2e 验证
- **vp92-frontend tracked 边界补漏** — `src/components/layout/PublicNavbar.vue` + `PublicFooter.vue` 等是否需要 tracked

---

## 🎯 当前项目状态

| 项 | 状态 |
|----|------|
| **最新 commit** | bd0c3a9 (v32) |
| **远程** | my = `github.com:kangtaze/xboard-ze.git` ✅ |
| **服务器** | root@154.3.38.212, Docker xboard 容器 ✅ |
| **前端** | vite build 1.42s, 部署完成, 0 console error |
| **CLAUDE.md** | v25/v26/v27/v31 铁律全部到位 |
| **vp92-frontend tracked** | 26 个 (v32 新入库 Dashboard.vue) |
| **MEMORY** | vp92-v25 → vp92-v32 共 8 个快照, 全部 ✅ |
| **下次唤醒** | 用户继续提供新需求（v33 候选或新方向） |

---

## 📚 关联文档

- **MEMORY.md** — 8 个版本快照索引 (v25/v26/v27/v28/v29/v30/v31/v32)
- `docs/deployment-guide.md` — 后端/前端/Bot/Worker 部署完整指南
- `docs/project-paths.md` — 项目目录结构
- `E:\jiedian\AGENTS.md` — jiedian 根级 AI 行为准则
- `E:\jiedian\vp92-flclash\AGENTS.md` — 项目级 backup（不依赖向上查找）

---

**Generated**: 2026-09-03 (v32 session)
**Next session wakeup prompt**:
```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件 (v32 完成)。
v32 = Dashboard.vue 主区完整 i18n + 32 新 key + vp92-frontend tracked 边界扩展。
按 v33 候选决定下一步。
```