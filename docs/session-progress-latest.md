# 📌 v31 Wakeup Snapshot — 2026-09-03 tutorial.* deadcode 清理

> **当前最新进度**（v31 完成 🎉）
> **会话类型**：v30 wakeup 候选的"en-US tutorial.* 78 缺失" → 验证后是 dead code → 删除清理
> **状态**：✅ 3 locales 完美对齐 433×3 + vite build 1.41s + 部署 + e2e 3 语言 + commit + push
> **完整 v31 细节**: MEMORY `vp92-v31-tutorial-deadcode-cleanup.md`

---

## ⚡ 一句话状态总结

```
v31 状态（2026-09-03）：
✅ 反预期发现: v30 wakeup 候选 "en-US 补 78 tutorial.*" 验证后是 zombie code
   - grep src/**/*.vue t('tutorial.') 0 命中
   - grep theme/*.blade.php data-i18n="tutorial.tabs/..." 0 命中
   - theme/tutorial.blade.php 完全硬编码中文，未走 i18n
✅ 用户决策: 删除 78 死 key (zh-CN/zh-TW 各 -105 行, en-US 本就无)
✅ 3 locales 完美对齐: 433 leaf keys × 3, 0 missing
✅ vite build 1.41s ✅ + index-8whh1liI→B4DW_UGC + curl 200 ✅ + e2e 3 语言切 0 error
✅ docker cp + docker exec cp blade (overlayfs 铁律)
✅ commit adfdfbe 入库 (1 file 改 1 行) + push my master (7a5f962..adfdfbe)
✅ memory vp92-v31-tutorial-deadcode-cleanup.md + MEMORY.md 索引入库
```

---

## 📁 v31 文件改动

| 文件 | 类型 | 关键改动 |
|------|------|---------|
| `src/locales/zh-CN.json` | untracked | 删除 lines 196-300 tutorial section (-105 行) |
| `src/locales/zh-TW.json` | untracked | 删除 lines 196-300 tutorial section (-105 行) |
| `src/locales/en-US.json` | untracked | 本就无 tutorial.*，无需操作 |
| `theme/dashboard.blade.php` | modified | line 37 JS hash: `8whh1liI`→`B4DW_UGC` (1 行) |

---

## 🚨 v31 新增铁律

### 1. Locale key 缺失 → 先 grep 验证，不要直接补
```
locale key X 缺失 → grep `t('X.')` / `$t('X.')` / `data-i18n="X.*"`
├─ 0 命中 → dead code → 从源头删除（节省 bundle + 消除死代码）
└─ N 命中 → 真缺失
    ├─ 引用方是 SPA 组件 → 翻译目标 locale
    └─ 引用方是 blade → wire-up blade 用 data-i18n（更大范围）
```

### 2. artisan chain 在 docker exec + ssh 内会 hang
**问题**：`docker exec xboard sh -c 'php artisan view:clear && php artisan cache:clear && php artisan octane:reload'` 在 ssh 链路上 ≥5 分钟不返回

**修复**：3 条 artisan 命令**分开独立执行**，每条独立 timeout + 独立 error check：
```bash
ssh server "docker exec xboard php artisan view:clear"   # <5s
ssh server "docker exec xboard php artisan cache:clear" # <5s
ssh server "docker exec xboard php artisan octane:reload" # <5s
```

### 3. git push 在某些 Windows 网络环境下需要显式 proxy
- CLAUDE.md 说"路由器透明代理"
- 实测：`git push my master` 报 `fatal: Recv failure: Connection was reset`
- 修复：`git -c http.proxy=http://127.0.0.1:7890 -c https.proxy=http://127.0.0.1:7890 push my master`
- **路由器透明代理的端口不一定全局透明**，push 前验证

---

## 📊 v31 验证记录

### Locales 对齐（关键指标）
```
counts (leaf keys): zh-CN 433 / zh-TW 433 / en-US 433
counts (total nested): 513 / 513 / 513
missing in zh-TW (vs zh-CN): []
missing in zh-CN (vs zh-TW): []
missing in en-US (vs zh-CN): []
extra in en-US (vs zh-CN): []
== 完美对齐 ==
```

### HTTP 验证
```
新 hash 200 OK ✅:
- /theme/VP92/assets/index-B4DW_UGC.js  → 200  (Content-Length: 50901)
- /theme/VP92/assets/i18n-BPipN7-r.js  → 200  (i18n chunk 大小未变 = dead code 删除)
- /theme/VP92/assets/vue-vendor-CohP-jTm.js → 200
- /theme/VP92/assets/index-CrfcEOxX.css → 200

/dashboard 渲染新 hash ✅:
- vue-vendor-CohP-jTm (line 16) unchanged
- index-CrfcEOxX.css (line 17) unchanged
- index-B4DW_UGC.js (line 37) ✅ NEW
```

### 容器内 blade 验证
```bash
docker exec xboard sh -c 'grep -E "index-|vue-vendor" /www/theme/VP92/dashboard.blade.php'
# vue-vendor-CohP-jTm ✓
# index-CrfcEOxX ✓
# index-B4DW_UGC ✓
```

### chrome-devtools e2e
```
1. /dashboard 默认 zh-CN → sidebar 中文正常 ✓
2. localStorage.fe_locale = en-US, reload → sidebar 全部英文 (Dashboard/Nodes/Plans/Orders/Tickets/AI Chat/Referral/Gift Card/Settings/Logout) ✓ 0 console error
3. localStorage.fe_locale = zh-TW, reload → sidebar 全部繁中 (控制面板/節點列表/方案計畫/我的訂單/客服支援/AI 客服/邀請返利/禮品卡/帳戶設定/退出) ✓
> 注: Dashboard 主区中文是 pre-existing Dashboard.vue 硬编码（v28 之前的范围，与 v31 无关）
```

---

## 🚧 v32 候选（next session 待决定）

- **`<title>` i18n** — v27b 已决策留作 v28+，dashboard.blade.php line 9 仍是硬编码 "VP92极速加速器"。可用服务端 Laravel `__()` 渲染
- **t(key, { interpolation }) 占位符审计** — v28 教训延伸：v30 Settings.vue 30+ 处 t()-化中是否有 timeoutRetry 类陷阱（key 无占位符却传了参数）
- **LanguageModal 入口去留** — v30 撤回 v29 Settings 卡，但 DashboardLayout 全局挂载仍保留。未来 v31+ 加「高级设置」入口时可继续使用
- **`/invite` 页面** — Settings.vue 引入 `router-link to="/invite"` 但用户没访问过，待 e2e 验证
- **`theme/tutorial.blade.php` i18n wire-up** — 彻底解决 tutorial 页英文用户看中文。50+ 字符串加 data-i18n 属性 + JS 读 localStorage.fe_locale + locales JSON 动态渲染
- **Dashboard.vue 主区 i18n** — Dashboard 主区仍是硬编码中文（e2e 显示）。当前最显眼的中英混杂

---

## 🎯 当前项目状态

| 项 | 状态 |
|----|------|
| **最新 commit** | adfdfbe (v31) |
| **远程** | my = `github.com:kangtaze/xboard-ze.git` ✅ |
| **服务器** | root@154.3.38.212, Docker xboard 容器 ✅ |
| **前端** | vite build 1.41s, 部署完成, 0 console error |
| **CLAUDE.md** | v25/v26/v27 铁律全部到位 + v31 新铁律待沉淀（locale 缺失先 grep、artisan chain 拆分、git push proxy 7890） |
| **MEMORY** | vp92-v25 → vp92-v31 共 7 个快照, 全部 ✅ |
| **下次唤醒** | 用户继续提供新需求（v32 候选或新方向） |

---

## 📚 关联文档

- **MEMORY.md** — 7 个版本快照索引 (v25/v26/v27/v28/v29/v30/v31)
- `docs/deployment-guide.md` — 后端/前端/Bot/Worker 部署完整指南
- `docs/project-paths.md` — 项目目录结构
- `E:\jiedian\AGENTS.md` — jiedian 根级 AI 行为准则
- `E:\jiedian\vp92-flclash\AGENTS.md` — 项目级 backup（不依赖向上查找）

---

**Generated**: 2026-09-03 (v31 session)
**Next session wakeup prompt**:
```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件 (v31 完成)。
v31 = tutorial.* 78 死 key 清理 + 3 locales 完美对齐 433×3。
按 v32 候选决定下一步。
```
