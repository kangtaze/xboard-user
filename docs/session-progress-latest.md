# 📌 v30 Wakeup Snapshot — 2026-09-03 header 语言下拉切换

> **当前最新进度**（v30 完成 🎉）
> **会话类型**：撤回 v29 Settings 语言卡 → header 铃铛左边 LanguageMenu 下拉 + Settings/LanguageModal 硬编码清理
> **状态**：✅ 5 files (2 new tracked + 3 modified) + 3 locales + 部署 + e2e + commit + push 完成
> **完整 v30 细节**: MEMORY `vp92-v30-header-lang-menu.md`

---

## ⚡ 一句话状态总结

```
v30 状态（2026-09-03）：
✅ 撤回 v29 Settings.vue「语言」卡 → 新建 LanguageMenu.vue header 铃铛左边下拉（1 步直达）
✅ AppTopbar.vue 接入 LanguageMenu（top-actions 和 notif-wrapper 之间 = 铃铛左边）
✅ Settings.vue 三件事：删 v29 语言卡 + 修 v29 orphan `</div>` + 修 v29 missing `</div>` (Third Party) + 30+ 处 t()-化
✅ LanguageModal.vue 硬编码清理（languages array / SELECT REGION / namespace 误用）
✅ 3 locales 补 20 key（languageModal 5 + languages 6 + settings.profile 2 + settings.password.messages 2 + settings.invite 4 + settings.bindings 1）
✅ vite build 1.40s ✅ + curl 3 新 hash 200 OK ✅ + 旧 hash cf-cache-status:HIT (Cache-Control:max-age=14400 自然过期)
✅ docker cp 铁律全程遵守（v25/v26/v27 主题路径）
✅ commit ba5365f 入库 (5 files changed, 363 insertions, 78 deletions) + push my master (27ed6c2..ba5365f)
✅ memory vp92-v30-header-lang-menu.md + MEMORY.md 索引入库
```

---

## 📁 v30 文件改动

| 文件 | 类型 | 关键改动 |
|------|------|---------|
| `src/components/layout/LanguageMenu.vue` | **NEW** | header 下拉组件，scoped document click 关闭 + 双写 locale |
| `src/components/layout/AppTopbar.vue` | **NEW TRACKED** | line 9-10 之间插入 `<LanguageMenu />`（铃铛**左边**） |
| `src/views/account/Settings.vue` | modified | 删 v29 Language 卡 + 修 orphan `</div>` + 修 missing `</div>` + 30+ 处 t()-化 |
| `src/components/common/LanguageModal.vue` | modified | 硬编码 languages 数组 → computed + SELECT REGION → t() + 修正 namespace 误用 |
| `theme/dashboard.blade.php` | modified | line 17 CSS: `D6abmHlS`→`CrfcEOxX` / line 37 JS: `Bg0NCkCU`→`8whh1liI` / vue-vendor `CohP-jTm` unchanged |
| `src/locales/{zh-CN,zh-TW,en-US}.json` | untracked | v25 铁律新增 20 key（不入库） |

---

## 🚨 v30 新增铁律（必读）

### 1. Vue i18n locale 双写铁律
```ts
function switchLocale(code: string) {
  locale.value = code            // CRITICAL: 触发 vue-i18n 响应式
  appStore.setLocale(code)       // CRITICAL: 持久化到 localStorage
  dropdownOpen.value = false
}
```
**单写 `appStore.setLocale()` 不触发 i18n update**（UI 不刷新）。
**单写 `locale.value = code` 不持久化**（F5 后丢失）。

### 2. vp92-frontend tracked 边界扩展（重要架构决策）
v30 后入库 **25 个文件**（新增 2）：
- AppTopbar.vue（从 untracked → tracked）— **修复 DashboardLayout 依赖漏洞**：v29 之后 DashboardLayout 引用的 AppTopbar 仍是 untracked，下次重建 vp92-frontend build 会失败
- LanguageMenu.vue（new）— 核心新组件

### 3. CF 边缘缓存 200 旧 hash 不是部署失败
```http
cf-cache-status: HIT
Cache-Control: max-age=14400   ← 4 小时 TTL
Age: 2954                       ← 49min 已缓存
```
容器内文件已删 (验证 OK)，CF 4h 后自然回源验证拿 404。**不要触发 CF API 强制 purge**。

---

## 🔧 v30 部署命令（完整流程参考）

```bash
# 1. 构建
cd E:\jiedian\vp92-frontend && npx vite build  # 1.40s

# 2. assets 上传
tar czf vp92-assets.tar.gz -C dist/assets .
scp vp92-assets.tar.gz root@154.3.38.212:/tmp/
docker cp /tmp/vp92-assets.tar.gz xboard:/tmp/
docker exec xboard sh -c 'cd /www/public/theme/VP92/assets && rm -rf * && tar xzf /tmp/vp92-assets.tar.gz'

# 3. blade hash 同步（v25/v26 铁律：docker cp + docker exec cp）
# 本地修改 theme/dashboard.blade.php line 17 CSS / line 37 JS
scp theme/dashboard.blade.php root@154.3.38.212:/tmp/v30-blade.php
docker cp /tmp/v30-blade.php xboard:/tmp/v30-blade.php
docker exec xboard sh -c 'cp /tmp/v30-blade.php /www/theme/VP92/dashboard.blade.php'
docker exec xboard sh -c 'cp /tmp/v30-blade.php /www/public/theme/VP92/dashboard.blade.php'

# 4. 清缓存 + reload
docker exec xboard sh -c 'cd /www && php artisan view:clear && php artisan cache:clear && php artisan octane:reload'

# 5. 验证
docker exec xboard sh -c 'grep -E "index-|vue-vendor" /www/theme/VP92/dashboard.blade.php'
curl -sI https://vp92vip.com/theme/VP92/assets/<新-hash>.js | grep 200
```

---

## 📊 v30 验证记录

### Locales 同步检查
```
counts: 510 510 432
missing in zh-TW: []  ← 完美对齐
missing in en-US: [78 个 tutorial.* 旧账, 与 v30 无关]
```

### HTTP 验证
```
新 hash 200 OK ✅:
- /theme/VP92/assets/index-8whh1liI.js  → 200
- /theme/VP92/assets/index-CrfcEOxX.css → 200
- /theme/VP92/assets/vue-vendor-CohP-jTm.js → 200

旧 hash 200 = CF cache HIT (非部署失败):
- /theme/VP92/assets/index-Bg0NCkCU.js → 200 (cf-cache-status: HIT, max-age=14400, Age: 2954)
```

### 容器内 blade 验证
```bash
docker exec xboard sh -c 'grep -E "index-|vue-vendor" /www/theme/VP92/dashboard.blade.php'
# index-CrfcEOxX.css (line 17)
# index-8whh1liI.js (line 37)
# vue-vendor-CohP-jTm.js (line 16)
```

---

## 🚧 v31 候选（next session 待决定）

- **en-US locale tutorial.* 78 缺失 key** — 旧账，与 v30 无关，e2e 英文环境会显示空字符串。是否在 v31 一次性补齐？
- **`<title>` i18n** — v27b 决策留作 v28+，dashboard.blade.php line 9 仍是硬编码 "VP92极速加速器"。可用服务端 Laravel `__()` 渲染
- **t(key, { interpolation }) 占位符审计** — v28 教训：key 必须真有占位符 (timeoutRetry 不需要 action 参数)。检查 Settings.vue 是否有此隐患
- **LanguageModal 入口去留** — v30 撤回 v29 Settings 卡，但 DashboardLayout 全局挂载仍保留。未来 v31 加「高级设置」入口时可继续使用
- **`/invite` 页面** — Settings.vue 引入 `router-link to="/invite"` 但用户没访问过，待 e2e 验证

---

## 🎯 当前项目状态

| 项 | 状态 |
|----|------|
| **最新 commit** | ba5365f (v30) |
| **远程** | my = `github.com:kangtaze/xboard-ze.git` ✅ |
| **服务器** | root@154.3.38.212, Docker xboard 容器 ✅ |
| **前端** | vite build 1.40s, 部署完成, CF cache 自然过期 |
| **CLAUDE.md** | v25/v26/v27 铁律全部到位 (overlayfs docker cp + locales 不入库) |
| **MEMORY** | vp92-v25 → vp92-v30 共 6 个快照, 全部 ✅ |
| **下次唤醒** | 用户继续提供新需求（v31 候选或新方向） |

---

## 📚 关联文档

- **MEMORY.md** — 6 个版本快照索引 (v25/v26/v27/v28/v29/v30)
- `docs/deployment-guide.md` — 后端/前端/Bot/Worker 部署完整指南
- `docs/project-paths.md` — 项目目录结构
- `E:\jiedian\AGENTS.md` — jiedian 根级 AI 行为准则
- `E:\jiedian\vp92-flclash\AGENTS.md` — 项目级 backup（不依赖向上查找）

---

**Generated**: 2026-09-03 (v30 session)
**Next session wakeup prompt**:
```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件 (v30 完成)。
v30 = header 语言下拉切换 + 撤回 v29 Settings 语言卡 + Settings/LanguageModal 硬编码清理。
按 v31 候选决定下一步。
```
