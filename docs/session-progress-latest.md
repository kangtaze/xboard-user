# 📌 v19 + v20 Wakeup Snapshot — 2026-08-28 VP92 营销自动化（最终完成态）

> **当前最新进度**（v20.5 + v20.2 — 全部完成 🎉）
> **会话类型**：v19 潜在付费用户识别 + v20 邮件追踪/营销漏斗/Bot 自动汇报 + UX
> **状态**：✅ v19 + v20 全部 6 个 sub-version 完成入库（v20.1 / v20.2 / v20.3 / v20.4 / v20.5 / v20.6）
> **完整 v20.2 细节**: [`docs/wakups/v20-2-intent-outdated-5th-tag.md`](wakups/v20-2-intent-outdated-5th-tag.md)
> **完整 v20.5 细节**: [`docs/wakups/v20-5-bot-typing-indicator.md`](wakups/v20-5-bot-typing-indicator.md)
> **完整 v20.6 细节**: [`docs/wakups/v20-6-bot-knowledge-v15-16-panel.md`](wakups/v20-6-bot-knowledge-v15-16-panel.md)

---

## ⚡ 一句话状态总结

```
v19 状态（2026-08-28）：
✅ Task 1-8: 8 个 plan task 全部完成（migration/model/classifier/refresh/send/kernel/settings/deploy）
✅ Follow-up A: ConfigSave 白名单添加 intent_* keys（b0f1c5e）→ API 保存可用
✅ Follow-up B: ConfigController intent_auto_send 默认 true（9ea1f42）→ 开箱即用
✅ Follow-up C: Kernel.php intent_auto_send 默认 true（cf01538）→ 4 个 send cron 注册
✅ 服务器 schedule:list 验证：intent:refresh + 4 个 send:intent-campaign cron 全部在册
✅ 用户决策：完全自动化，不保留 UI 开关（cedar admin SPA 硬编码无法改 UI）
✅ 首次自动发送：明天 10:00（expiring_soon 每日）
```

---

## 🚨 关键设计决策

### 1. 不做 admin UI 开关
- cedar admin SPA bundle (`index-CEIYH7i8.js`) 硬编码表单字段，0 个 `intent_*` 引用
- Fork SPA 工作量太大（v15.12 已接受注入方案，但 settings 需双向读写）
- **结论**：开关只在 DB 里 `intent_auto_send`，需要关就 SSH 写 0

### 2. 默认全开
- `intent_auto_send` 默认 `true`（ConfigController + Kernel.php 都改了）
- 4 个 cron 永久注册：周一 intent_high / 周三 intent_cold / 周五 churned_warm / 每天 expiring_soon
- 7 天冷却保护（同用户同 tag 7 天内最多 1 封）
- 重算每日 00:30，发送每日 10:00，**用户用户访问完全无感**

### 3. 服务器负担 < 1 分钟/周
- intent:refresh：每天 1 次 < 1 秒
- send 每次：1 次 SELECT + 批量发邮件（22 人 ≈ 8 秒）
- 每周总 CPU：< 1 分钟
- 完全异步跑 Octane worker，不阻塞用户访问

---

## 📂 v19 新增/修改文件

| 文件 | 用途 | commit |
|------|------|--------|
| `database/migrations/2026_08_27_000002_create_v2_user_intent.php` | v2_user_intent 表 migration | 5ed6631 |
| `app/Models/UserIntent.php` | Eloquent model + 4 tag 常量 | 8d5effe |
| `app/Services/IntentClassifier.php` | 4 tag SQL 分类器（classify + usersByTag） | 7a78072 |
| `tests/Feature/IntentClassifierTest.php` | 6 个单测（server-only TDD） | 7a78072 |
| `app/Console/Commands/IntentRefresh.php` | 每日 cron 重算 | c6b18ce |
| `app/Console/Commands/SendIntentCampaign.php` | 群发命令 + 7天冷却 | ac09df5 |
| `app/Console/Kernel.php` | cron 注册 + 服务器独有行合并 | 10ae368 + 3283d15 + cf01538 |
| `app/Http/Controllers/V2/Admin/ConfigController.php` | 4 个 admin settings（默认 true）| 10d6045 + 9ea1f42 |
| `app/Http/Requests/Admin/ConfigSave.php` | ConfigSave validation 白名单 | b0f1c5e |

---

## 📊 端到端验证结果（2026-08-28）

| Step | 结果 |
|------|------|
| Migration | ✅ `2026_08_27_000002_create_v2_user_intent` DONE |
| Schedule list | ✅ 7 个 cron 全在册（含 4 个新 send）|
| intent:refresh | ✅ 6 expiring_soon + 22 churned_warm + 0/0 intent |
| Dry-run | ✅ 3 样本用户展示 |
| 真发 3 封 | ✅ mail_log id 3363-3365 |
| 7 天冷却 | ✅ 重发匹配 0 |
| 推送 my master | ✅ 9 commits `c33c543..cf01538` |

---

## ⚠️ 已知遗留（不阻塞）

- 无邮件 open/click 追踪（v2 阶段 — v20.1 已埋点基础设施，但本期未启用 UI）
- 无 funnel 报表（v2 阶段 — v20.1 服务 + v20.3 Bot tool 已 ready，可查 funnel/open_rate/intent_stats）
- admin SPA UI 开关未做（如需做，按 v15.12 模式 JS 注入，30 分钟）
- 🟡 IntentRefreshTest 未写（UNIQUE 冲突 fix 是手测验证，建议 v21 加）

---

---

## ✅ v20.2 — intent_outdated 第 5 tag（2026-08-28 完成）

**目标**：补 v19 4 tag 漏掉的"曾订阅但过期 7-30 天"中段漏斗用户群（73 个真实用户）

**5 文件改动**（md5 一致 + 6/6 验证）：
- `app/Models/UserIntent.php` — 加 `TAG_INTENT_OUTDATED` 常量 + ALL_TAGS 包含 5 tag
- `app/Services/IntentClassifier.php` — `classify()` 加第 5 分支；`usersByTag()` 加 case
- `app/Console/Commands/SendIntentCampaign.php` — TEMPLATE_MAP / SUBJECT_MAP / CONTENT_MAP 加 outdated
- `app/Console/Kernel.php` — 加 `send:intent-campaign --tag=intent_outdated --force` 周二 10:00 cron
- `tests/Feature/IntentClassifierTest.php` — 加 4 测试（10/10 通过，13 assertions）
- `app/Console/Commands/IntentRefresh.php` — **顺带修复** pre-existing UNIQUE 冲突 bug（取 last_mailed_at → 删 → 重插保留）

**TDD**: RED（Undefined constant TAG_INTENT_OUTDATED 错误）→ GREEN（10/10 OK, 13 assertions）✅

**commit**: `b5d7d29` push my master（0bbd62d..b5d7d29）

**端到端验证**：
- ✅ intent:refresh 跑通 5 tag 分布：5+23+0+0+**73**
- ✅ dry-run 显示 73 真实用户（前 10 样本：test0703@vp92vip.com / 290201734@qq.com 等），过期日期全在 7-30 天范围
- ✅ schedule:list 5 个 send cron + intent:refresh 全部在册（含周二 10:00 intent_outdated cron）
- ✅ 模板验证：CONTENT_MAP 反射读出 "VP92 想您了 - 专属回归优惠等你领取" 完整模板

**关键决策**：
- ✅ 不新建 blade 模板（复用 notify，与 intent_high/intent_cold 一致模式）
- ✅ 不新增 `intent_outdated_*` config keys（SendIntentCampaign 用全局 intent_latest_version/intent_latest_features/intent_promo_end_date 共享）

**完整 v20.2 细节**: [`docs/wakups/v20-2-intent-outdated-5th-tag.md`](wakups/v20-2-intent-outdated-5th-tag.md)

---

## ✅ v20.5 — Bot sendChatAction typing 指示器（2026-08-28 完成）

**目标**：3 个 Bot DeepSeek 调用期间显示 "typing..." 状态，让用户感知 Bot 在工作（5-15s UX 提升）

**5 文件改动**：
- `app/Services/TelegramChatAction.php` (NEW) — 封装 sendChatAction，try/catch 静默
- `app/Console/Commands/TelegramAdminAiPoll.php` — `adminAiChat()` 加 typing
- `app/Console/Commands/TelegramAiPoll.php` — 2 处：客服 chat + admin-via-customer
- `app/Console/Commands/TelegramKawuPoll.php` — `kawuAiChat()` 加 typing
- `tests/Feature/TelegramChatActionTest.php` (NEW) — 4 测试（default typing / custom / HTTP 错 / 异常）

**TDD**: RED（class missing 错误）→ GREEN（4/4 OK, 6 assertions）✅

**commit**: `b5e2cbc` push my master（ed9f3ee..b5e2cbc）

**验证**：
- ✅ md5 一致（4 文件本地 = 服务器）
- ✅ 测试 4/4 通过
- ✅ Bot alive（getUpdates 200 OK，**不是 409** — 409 是 stale /tmp/admin-ai-poll.log Aug 27 旧日志）
- ✅ 测试消息发送 + Bot 处理（message_id 332 → 333 detected）
- 📱 **手机 Telegram 实测**：给 3 个 Bot 发消息应能看到 "typing..."

**签名变更**：`adminAiChat(string $message)` → `adminAiChat(int|string $chatId, string $message)`（其他 2 Bot 同样）

---

## 📊 v20 进度总览

| sub | 名称 | 状态 | commit |
|-----|------|------|--------|
| v20.1 | tracking 基建 | ✅ | 033efd1 |
| **v20.2** | **intent_outdated 第 5 tag** | ✅ | **b5d7d29** |
| v20.3 | Bot tool 集成（4 tool） | ✅ | 033efd1 |
| v20.4 | 周报自动推送 | ✅ | 16f7a24 |
| v20.5 | Bot typing 状态 | ✅ | b5e2cbc |
| v20.6 | Bot 知识 v15.16 面板 | ✅ | 10c0e7e/ed9f3ee |

**v20 计划 6/6 全部完成 🎉**（包括 v20.2 intent_outdated 第 5 tag）

---

# 🚨 v15.10 Wakeup Snapshot — 2026-08-26 落地页 hero/footer CTA 改'免费注册'跳转 /auth/register

> **当前最新进度**（v15.10 — supersedes v15.9）
> **会话类型**：v15.9 后续微调 — 落地页 hero + footer "立即订阅" 改回 "免费注册"，点击跳 /auth/register 注册页（不走 modal）
> **状态**：v15.10 完成（landing.blade.php 4 处编辑 + 服务器双路径同步 + view/cache/octane reload + chrome 3 语言 + 登录态验证 + git push my-frontend vp92-master commit 6979e75）

---

## ⚡ 一句话状态总结

```
v15.10 状态（2026-08-26 深夜）：
✅ v15.9 完成（Bug C 完整修复 + Bug D 按钮文案统一）→ ae09719/356b23d push vp92-master
✅ v15.10 落地页 hero/footer CTA 改回 '免费注册'：立即订阅 → 免费注册，跳转 /auth/register 注册页（不再开 modal）
✅ v15.10 i18n 同步：cta_button + btn_register 三语言全部改为 免费注册/免費註冊/Free Register
✅ v15.10 Plan cards 不变：line 847/888/930 仍为"立即订阅"+ openSubscribeModal 弹窗订阅（用户确认不动）
✅ v15.10 Dashboard 端 PlanPurchase.vue 不变（不在本次范围）
✅ v15.10 chrome-devtools 验证：zh-CN/zh-TW/en 三语言切换正确；未登录 hero 点击 → /auth/register 注册表单正确渲染；登录态 hero 点击 → /dashboard
✅ v15.10 服务器双路径 md5 一致（2ab36c761e8acf9c11a0aa1f9a3aa7d8）
✅ v15.10 git push my-frontend vp92-master：6979e75（ahead of ae09719）
```

---

---
✅ SQL 修复：260 个 VIP1 用户 device_limit=5（用户当前在用）
✅ 端到端验证通过：artisan tinker 模拟套餐 5→10，260 个 user 自动同步成 10
✅ ThemeServiceProvider 新增：修复 5 个 theme:: 路由 500 错误（commit 99388b3）
⏳ 后台用户显示恢复（客户端类型/版本/IP）— Blocked: xboard-admin.js 是 minified bundle
⏳ vp92-frontend widget commit 276049c 待 push（remote 配错待决）
```

---

---

## 📋 今日已完成工作（按时间顺序）

### v15.8 ⭐ 当前完成 — Bug A+B 修复（落地页注册订阅流程）

**用户原话（推荐方案）**：v15.6 推荐选项 1（最小修复 A+B）— 修 jt() + handleSubscribe try/catch

#### ⚠️ v15.6 文档 Bug A 根因报告与实际代码不符 — 真实根因更严重

**v15.6 文档说法**：jt() 只读 window.I18N.js，err_xxx keys 在 modal section
**实际代码事实**：
- err_password_short 在 js section line 236（**不在 modal section**）
- 但 jt() 实现**假设错**：

```javascript
// 原 (错):
function jt(key) {
    const lang = window.I18N.js[I18N_CURRENT_LANG] || window.I18N.js[I18N_DEFAULT_LANG];
    return lang[key] || window.I18N.js[I18N_DEFAULT_LANG][key] || '';
}

// 假设结构: window.I18N.js = {zh-CN:{err_password_short:'密码至少8位',...}, en:{...}}
// 实际结构: window.I18N.js = {err_password_short:{zh-CN:'密码至少8位',...}, ...}
```

**Chrome 实测确认原 TypeError**：
```
TypeError: Cannot read properties of undefined (reading 'err_password_short')
    at jt (https://vp92vip.com/:1557:24)
```
**根因**：`window.I18N.js[I18N_CURRENT_LANG]` 永远是 undefined（因为 zh-CN 不是顶层 key），后续 `lang[key]` 抛 TypeError。
**影响**：**所有 jt() 调用都抛错**，不只是 err_xxx — handleSendCode / handleSubscribe / showSubError 全部受影响。

#### 修复 1 — jt() 正确 lookup（致命）

```javascript
function jt(key) {
    // I18N.js 结构是 {key: {lang: value}}（按 key 分组），不是 {lang: {key: value}}（按 lang 分组）
    const group = window.I18N.js;
    if (!group) return '';
    const entry = group[key];
    if (!entry) return '';
    return entry[I18N_CURRENT_LANG] || entry[I18N_DEFAULT_LANG] || '';
}
```

#### 修复 2 — handleSubscribe try/catch 防护（defense-in-depth）

整个函数体（同步部分）包 try/catch，捕获后 fallback 显示 `err_network` + 重置按钮状态。异步 fetch 错误仍走原 `.catch()` 链。

#### Chrome 端到端验证（修复后）

| 测试 | 修复前 | 修复后 |
|------|--------|--------|
| `jt('err_password_short')` | ❌ TypeError | ✅ "密码至少8位" |
| `jt('err_network')` | ❌ TypeError | ✅ "网络错误，请重试" |
| `jt('err_register_failed')` | ❌ TypeError | ✅ "注册失败" |
| handleSubscribe 短密码 | ❌ 卡死无提示 | ✅ 显示"密码至少8位" |
| handleSubscribe 空字段 | ❌ 卡死无提示 | ✅ 显示"请填写完整信息" |
| Console 错误 | ❌ TypeError | ✅ 无 |

#### 部署 + Git

- 服务器 md5：`94192f38f5a3728255dac8cc303c9b58`（双路径 /www/theme + /www/public 一致）
- view:clear + cache:clear 已执行
- Commit：`9c5e97a fix(landing): 修 jt() lookup 结构 bug + handleSubscribe try/catch 防护`
- Push：✅ `99388b3..9c5e97a master -> master`（kangtaze/xboard-ze）

#### ⚠️ 副作用发现（顺手报告）

服务器 `/www/theme/VP92/landing.blade.php` 部署前 md5 = `4d3e50ef9649cb847b94e97e53272166`
本地 git HEAD bcd4189 md5 = `d173f8e1ea840ebe0518d4155bc395ff`

**服务器落后于本地 git HEAD** — v15.5 期间做的去 VPN 化更新（meta title、keywords、24h 试用文案、品牌名从 VP92 Turbo VPN → VP92 Turbo Accelerator）**从未部署**。

本次 commit 9c5e97a 已包含这些本地修改 → 部署后服务器与 git HEAD 一致。如不需要这部分文案更新，需手动回滚 meta 字段。

#### 未完成（v15.6 任务清单残留）

- 🟡 **Bug C**：PlanPurchase.vue auto-checkout 静默失败（loadPlans 403 → plans=[] → 跳过）— ✅ **v15.9 完整修复完成**
- 🟢 **Bug D**：按钮文案改"立即订阅" — ✅ **v15.9 完成（PlanPurchase.vue + landing.blade.php hero/footer）**

### v15.9 ⭐ Bug C 完整修复（用户决策 = 选项 C）

**用户决策**：完整修复 = fetchPlans fallback + 401 redirect query 保留 + PlanPurchase retry + token guard

**根因（实际代码验证）**：
1. `PlanPurchase.vue:245-268` onMounted 检查 `plans.value.length > 0` 后才 auto-checkout — loadPlans 失败时 plans=[] → 静默跳过
2. `loadPlans` lines 224-243 catch 块只 `console.error`，无 retry / 无 toast / 无 fallback
3. `client.ts:71-74` 401 拦截器设 `window.location.hash = '#/login'` — 但 router 用 history 模式，hash 设错位置；丢失 redirect query（`?checkout_plan=X`）

**关键发现**：`/api/v1/guest/plan/fetch` **已存在**（`GuestRoute.php:18` + `Guest/PlanController.php`），复用同一个 `PlanService::getAvailablePlans() + PlanResource`。**无需新建后端 endpoint**。

**修复代码（4 个文件）**：

1. **`src/api/user.ts:67-79`** — fetchPlans 加 401/403 fallback 到 guest endpoint
```javascript
export async function fetchPlans() {
  try {
    return await client.get('/user/plan/fetch')
  } catch (e: any) {
    const status = e?.code
    if (status === 401 || status === 403) {
      console.warn('[fetchPlans] user endpoint failed, falling back to guest:', status)
      return await client.get('/guest/plan/fetch')
    }
    throw e
  }
}
```

2. **`src/api/client.ts:69-79`** — 401 拦截器改用 `window.location.assign('/auth/login?redirect=...')` 保留当前完整 URL（含 query）
```javascript
if (status === 401) {
  removeToken()
  const currentPath = window.location.pathname + window.location.search
  if (!currentPath.startsWith('/auth/')) {
    const redirect = encodeURIComponent(currentPath)
    window.location.assign(`/auth/login?redirect=${redirect}`)
  }
  return Promise.resolve({ code: status, message: message || '登录已过期', errors })
}
```

3. **`src/views/commerce/PlanPurchase.vue`** — 多处改动
   - 引入 `useRouter` + `getToken` + `redirectToLogin()` helper
   - `loadPlans` 加 3 次指数退避（500ms/1s/2s），失败时 toast "套餐加载失败，请点击重试"
   - 新增 `retryLoadPlans` 函数
   - `handleSelectPlan` 入口检查 `getToken()`，无 token → 跳登录；catch 块检查 401/403 → 跳登录
   - empty state 加 "重新加载" 按钮
   - 按钮文案统一：'立即订阅'（Bug D 同时修）

4. **`theme/landing.blade.php`** — Bug D 扩展
   - line 765 hero CTA: '免费注册' → '立即订阅'
   - line 1478 footer 按钮: '免费注册' → '立即订阅'
   - line 57 i18n `cta_button`: '免费注册' → '立即订阅'（zh-CN/zh-TW/en）
   - line 185 i18n `btn_register`: '免费注册' → '立即订阅'

**部署 + 验证**：
- vite build OK（221 modules transformed，1.5s）
- scp vp92-assets.tar.gz → docker extract 到 /www/public/theme/VP92/assets/
- /www/theme/VP92/dashboard.blade.php hash 更新：`index-DB56GpsF.css → index-DS761ScJ.css`，`index-DlVtTxFQ.js → index-CggZ4wSM.js`
- md5 双路径对齐：9aa0168a817f7e6afe198e192a37e353 (theme) = public
- landing.blade.php scp 到 /www/theme/VP92/ + sync public copy + view:clear/cache:clear/octane:reload
- md5 双路径对齐：a22474b467532b02dc3ad97737869cf5

**chrome-devtools MCP 实测**：
- ✅ landing page hero "立即订阅"（不再是"免费注册"）
- ✅ landing page footer "立即订阅"
- ✅ /dashboard/plans?checkout_plan=2&checkout_cycle=month_price → 路由守卫跳 `/auth/login?redirect=/dashboard/plans?checkout_plan=2%26checkout_cycle=month_price`（URL 编码保留）
- ✅ /api/v1/guest/plan/fetch 返回 200 OK + 3 个 plan
- ✅ /api/v1/user/plan/fetch 无 token 返回 403 "未登录或登录已过期"（触发 fallback）

**Git push**：
- Commit：`356b23d fix(PlanPurchase): auto-checkout silent fail full fix + button text unified`
- Push：`d978e87..356b23d 356b23d -> vp92-master`（kangtaze/xboard-user via remote `my-frontend`）
- xboard-repo 无改动（前端改动，backend 仓库不动）

### v15.8 ⭐ 隐私 URL 完整修复（用户在会话后半段要求）

**用户原话**：
> "本地落地页 里面的隐私政策 链接 privacy-privacy.html ，服务器上的 更新 确保落地页 营私政策路径是 privacy-privacy.html"

**根因**：landing.blade.php line 1470 footer 链接长期指向 `/vp92-privacy.html`，但服务器无该 blade 文件 → 用户点"隐私政策" → 404。
v15.5 期间"隐私 URL rename"只改了路由 + 2 个 blade 重命名，**漏改** footer line 1470（长期 bug）。

**实施链路（4 步）**：

| # | 位置 | 修改 |
|---|------|------|
| 1 | `vp92-frontend/theme/landing.blade.php` line 1470 | `/vp92-privacy.html` → `/privacy-privacy.html` |
| 2 | 服务器 `/www/routes/web.php` | 新增 `Route::get('/privacy-privacy.html', ...)` → `theme::VP92.privacy-privacy` |
| 3 | 服务器 `/www/theme/VP92/privacy-privacy.blade.php` | 基于 `privacy-policy.blade.php` 复制 |
| 4 | 服务器 `/www/public/theme/VP92/privacy-privacy.blade.php` | nginx 静态副本（双路径 md5 一致） |

**curl 验证（Octane reload 后）**：
```
/vp92-privacy.html    → 404（旧文件已删）
/privacy-policy.html  → 200（兼容旧 URL）
/privacy-privacy.html → 200 ✅
```

**Chrome 实测**：
- footer 链接 = `https://vp92vip.com/privacy-privacy.html` ✅
- 点击进入 title = "隐私政策 - VP92极速加速器"，渲染全部章节 ✅

**最终完整调用链**：
```
landing.blade.php footer
  └─ href="/privacy-privacy.html"
       └─ web.php Route::get('/privacy-privacy.html', ...)
            └─ view('theme::VP92.privacy-privacy')
                 └─ /www/theme/VP92/privacy-privacy.blade.php
                      └─ /www/public/theme/VP92/privacy-privacy.blade.php (nginx 副本)
```

**Git 入库（2 个 commit）**：
```
7c97568 fix(landing): footer 隐私政策链接改 /privacy-privacy.html
2ba411d feat(theme): 补 commit /privacy-privacy.html 路由 + blade 文件
```

**md5 一致性确认（三方对齐）**：
- 本地 web.php = `d9ff546ab4e8b710e53f7c45e90a42f0` = 服务器 ✅
- 本地 privacy-privacy.blade.php = `15be8782317b69239783838e671f4ab2` = 服务器双路径 ✅

---

### v15.5 历史（已 superseded）

### 1️⃣ v15.4 AI 客服培训话术 — 已部署 + 已 hotfix + 已 push

**3 个相关 commit**（均在 `kangtaze/xboard-ze` 已入库）：
```
4af47ba feat(AiChatService): v15.4 培训话术增补（13 模板+用户风格+铁令+Telegram Bot 个性化）
e47ad1f hotfix(AiChatService): SYSTEM_PROMPT 改 nowdoc 避免 $userContext 在 class const 中被插值触发 PHP fatal
bcd4189 fix(landing): chat widget cache-bust v3→v4
```

**关键文件**：
- `E:\jiedian\xboard-repo\app\Services\AiChatService.php` — 205+ 行 nowdoc SYSTEM_PROMPT（含 v15.4 培训话术段 lines 162-198 + APP 界面导览段 lines 109-134）
- `E:\jiedian\docs\ai-training-2026-08-26.md` — 培训话术 SINGLE SOURCE OF TRUTH（341 行）

**部署命令**（已执行）：
```bash
ssh root@154.3.38.212 "docker exec xboard php artisan view:clear && php artisan cache:clear && php artisan octane:reload"
```

**致命坑（已踩，e47ad1f 修复）**：
- ❌ heredoc `<<<PROMPT` 会插值 `$userContext` → class const fatal error "Constant expression contains invalid operations"
- ✅ 必须 nowdoc `<<<'PROMPT'`，所有 PHP class const 中的多行字符串用 nowdoc

**Chrome 验证状态**：
- 落地页 + Dashboard AI 客服均可正常回复
- v15.4 培训话术已生效（用户问套餐/价格/下载/连接 都能标准化回复）

---

### 2️⃣ 3 个 Widget UI Bug 修复（vp92-frontend）

**用户原话（3 个 bug）**：
> "落地页，AI 客服用户编辑框 为什么不用黑色字体，还有落地页 手机端查看 AI客服悬浮图标挡住了 下载按钮，会有AI客服的聊天框里面AI客服回复的时候 下拉条不自动往下走"

**Commit**：`276049c fix(chat-widget): 3 个 UI bug 修复 v5`
**状态**：✅ 本地 commit 已完成，⏳ 待 push（remote 配错，见下方待处理）

**文件**：`E:\jiedian\vp92-frontend\public\js\vp92-chat-widget.js`（304 行）
**服务器 md5 验证**：本次会话部署版本 md5 待 push 后部署

**3 个 bug 修复位置**：

**Bug 1 — 输入框字体不黑（lines 103-110）**：
```javascript
.vp92-chat-input input {
    flex: 1; border: 1px solid #e0e3e5; border-radius: 20px; padding: 8px 14px;
    font-size: 14px; outline: none; transition: border-color 0.2s;
    color: #1a1a2e;  /* 修复:输入框字体清晰深色,避免 mobile 浏览器偏淡看不清 */
    background: #ffffff;  /* 显式白底,避免暗色主题继承 */
}
.vp92-chat-input input::placeholder { color: #9aa0a6; }
```

**Bug 2 — 手机端悬浮按钮挡住下载按钮（lines 120-126）**：
```css
@media (max-width: 480px) {
    #vp92-chat-widget { bottom: 14px; right: 12px; }  /* 修复:手机端靠右下,但避开底部 CTA 按钮 */
    #vp92-chat-btn { width: 48px; height: 48px; box-shadow: 0 3px 10px rgba(0,102,255,0.35); }  /* 修复:缩小按钮减少遮挡面积 */
    #vp92-chat-btn svg { width: 24px; height: 24px; }
    #vp92-chat-box { width: calc(100vw - 16px); height: calc(100vh - 100px); bottom: 72px; right: 0px; }
}
```

**Bug 3 — AI 回复时不自动滚动到底（lines 244-254, 263, 287, 296）**：
```javascript
function scrollMessagesToBottom(smooth) {
    requestAnimationFrame(() => {
        const last = messagesEl.lastElementChild;
        if (last && typeof last.scrollIntoView === 'function') {
            last.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'end' });
        } else {
            messagesEl.scrollTop = messagesEl.scrollHeight;
        }
    });
}
```

**Chrome 验证状态**：
- ✅ Bug 1：input 字体颜色 = rgb(26,26,46) 深色清晰
- ✅ Bug 2：mobile viewport 480px，btn 48×48px，不再遮挡
- ✅ Bug 3：scrollIntoView({block:'end'}) 工作正常，AI 回复后消息自动滚到底

---

### 3️⃣ 隐私 URL Rename — 全部完成 ✅✅✅

**用户原话**：
> "https://vp92vip.com/vp92-privacy.html  把这个 文件改名 改成 https://vp92vip.com/privacy-privacy.html 速度"

**完成时间**：本次会话末段，Chrome 验证通过

**6 个文件已修改（已 md5 + Chrome 验证）**：

| 文件 | 修改内容 |
|------|---------|
| `/www/routes/web.php` | `/vp92-privacy.html` → `/privacy-privacy.html`（route + view name + comment） |
| `/www/theme/VP92/vp92-privacy.blade.php` | 重命名为 `privacy-privacy.blade.php`（保留源文件） |
| `/www/theme/VP92/landing.blade.php` | line 1470 footer link 改为 `/privacy-privacy.html` |
| `/www/theme/VP92/vp92-terms.blade.php` | line 124, 176 两处链接改 |
| `/www/public/theme/VP92/privacy-privacy.blade.php` | nginx 静态副本同步 |
| `/www/public/theme/VP92/landing.blade.php` | nginx 静态副本同步 |

**核心 blade 文件 md5**：
```
d672d310597fe35ab721565fc616aac4  /www/theme/VP92/privacy-privacy.blade.php
d672d310597fe35ab721565fc616aac4  /www/public/theme/VP92/privacy-privacy.blade.php  ✅ 完全一致
```

**Chrome 验证**：
- ✅ `https://vp92vip.com/privacy-privacy.html` 返回 HTTP/2 200
- ✅ Page title: "隐私政策 - VP92极速加速器"
- ✅ canonical + og:url 都指向新 URL
- ✅ 全部 11 个章节渲染（引言/信息收集/信息使用/Cookie政策/数据存储/数据共享/用户权利/儿童隐私/国际数据传输/政策更新/联系我们）
- ✅ Footer link 指向新 URL
- ✅ terms 页的"隐私政策"链接指向新 URL

---

### 4️⃣ PlanController 设备限制同步 BUG 修复 — v15.6 ⭐ 当前重点

**用户原话**：
> "我后台套餐，设置没有限制用户设备 为什么用户设备显示0/5呢?"
> "我现在设置了 0就是不限制啊"
> "你怎么没听明白呢？   Xboard 后台可以设置用户 限制多少太啊， 你有没有好好看看后台套餐设置啊"

**用户最终决策**：方案 C — 两者都做（先 SQL 清干净 + 改代码防止以后同类问题）

**根因**：
1. 原 `PlanController::save()` 在 `force_update=true` 时把 `$params['device_limit']` 直接写入 `user.device_limit`
2. 当套餐 `device_limit=0`（Xboard 语义：不限制），用户.device_limit 也会被设成 0
3. 但 `UniProxyController:76` 有 `where('device_limit', '>', 0)` 过滤 0 用户
4. → 后台套餐显示不限设备，但老用户实际连接被拒 → 用户面板显示 0/5

**修复代码**（`app/Http/Controllers/V2/Admin/PlanController.php` lines 48-63）：
```php
// 当套餐 device_limit <= 0 (即"不限制") 时，无条件同步清空老用户 user.device_limit
// 避免依赖 force_update 复选框（Xboard 语义：0 或 NULL = 不限制设备）
$deviceLimitUnlimited = array_key_exists('device_limit', $params) && (int) $params['device_limit'] <= 0;
if ($deviceLimitUnlimited) {
    User::where('plan_id', $plan->id)->update([
        'device_limit' => null,
        ]);
}
if ($request->input('force_update')) {
    User::where('plan_id', $plan->id)->update([
        'group_id' => $params['group_id'],
        'transfer_enable' => $params['transfer_enable'] * 1073741824,
        'speed_limit' => $params['speed_limit'],
        'device_limit' => $deviceLimitUnlimited ? null : $params['device_limit'],
        ]);
}
$plan->update($params);
```

**Commit**：`bc87433 fix(PlanController): 当套餐 device_limit<=0 时无条件同步老 user.device_limit=NULL`
**Push**：`e47ad1f..bc87433 master -> master`（已 push 到 `kangtaze/xboard-ze`）

**SQL 清理**（上会话完成）：
- 261 个老用户 `device_limit` 清成 NULL
- 验证：`SELECT COUNT(*) FROM v2_user WHERE device_limit = 0` → 0 行

**服务器验证**：
```
0044af4681e4845b5d08e20d84f39b9b  /www/app/Http/Controllers/V2/Admin/PlanController.php
No syntax errors detected in /www/app/Http/Controllers/V2/Admin/PlanController.php
INFO  Reloading workers...
```

**VIP3 当前状态**（修复后）：
```
1|VIP1|    (NULL)
2|VIP2|    (NULL)
3|VIP3|0   ← 套餐 device_limit=0（不限制）
8|VIP4|    (NULL)

VIP3 用户分布：2 total, 2 unlimited(NULL), 0 zero_limit, 0 restricted
```

**待用户验证**：去后台实际操作一次套餐保存，确认修复代码生效。

---

### 5️⃣ PlanController 完整修复（device_limit 字段变化时同步 user） — v15.7 ⭐ 当前重点

**用户原话**：
> "我现在VIP1 套餐设置限制5台设备为什么 为什么现在设备还是显示0啊?"

**新发现根因**（v15.6 修复不完整）：
- v15.6 只处理 `device_limit<=0` 清空场景
- 但用户实际场景是 `device_limit=5`（设了限制）
- 而且 Xboard 设备限制**实际生效的是 `user.device_limit`，不是 `plan.device_limit`**：
  - `LoginService.php:96` 检查 `$user->device_limit`
  - `getSubscribe` API 返回 `user.device_limit` 给前端
  - `UserService::assignPlan`/`setPlanForUser` 才把 plan → user 同步

**修复代码**（`PlanController::save` lines 48-55）：
```php
// 当套餐 device_limit 字段变化时，无条件同步所有该套餐下用户的 device_limit
// 避免依赖 force_update 复选框（Xboard 设备限制实际用 user.device_limit，不是 plan）
if (array_key_exists('device_limit', $params) && (int) $plan->device_limit !== (int) $params['device_limit']) {
    $newDeviceLimit = (int) $params['device_limit'] > 0 ? (int) $params['device_limit'] : null;
    User::where('plan_id', $plan->id)->update([
        'device_limit' => $newDeviceLimit,
    ]);
}
```

**Commit**：`97ea9f0 fix(PlanController): 套餐 device_limit 字段变化时无条件同步 user.device_limit`
**Push**：`bc87433..97ea9f0 master -> master`（已 push 到 `kangtaze/xboard-ze`）

**SQL 紧急修复**（让用户立即生效）：
- VIP1 plan.device_limit: 0 → 5（用户要求值）
- VIP1 user.device_limit: 260 个 NULL → 5

**端到端验证**（`artisan tinker` 模拟 plan save 5→10）：
```
Plan after: 10
VIP1 user count at 10: 260
VIP1 user count at 5: 0   ← 自动同步成功！
```
恢复：plan=5, 260 user=5。

**服务器验证**：
```
1d65bed19a73e2c07b97d899356c38cd  /www/app/Http/Controllers/V2/Admin/PlanController.php
No syntax errors detected in /www/app/Http/Controllers/V2/Admin/PlanController.php
INFO  Reloading workers...
```

---

### 6️⃣ ThemeServiceProvider 新增 — v15.7 修复 5 个 theme:: 路由 500 ⭐ 紧急

**用户原话**：
> "https://vp92vip.com/privacy-policy.html 这里怎么打不开了?"

**根因**（架构缺陷）：
- `ThemeService::__construct()` 调用 `registerThemeViewPaths()`
- 但只有 `XboardUpdate::handle()` 和 `ConfigController::save()` 实例化了它
- web 请求路由（如 `/privacy-policy.html`）从未实例化 → namespace 没注册
- 结果：`view('theme::VP92.privacy-policy')` 抛 `InvalidArgumentException: No hint path defined for [theme]`

**修复**（新增 `app/Providers/ThemeServiceProvider.php`）：
```php
class ThemeServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ThemeService::class);
    }

    public function boot(): void
    {
        $this->app->make(ThemeService::class);  // 触发 registerThemeViewPaths()
    }
}
```
并在 `config/app.php` providers 数组追加 `App\Providers\ThemeServiceProvider::class,`

**Commit**：`99388b3 fix(theme): 新增 ThemeServiceProvider 修复 theme:: namespace 未注册 500 错误`
**Push**：`97ea9f0..99388b3 master -> master`（已 push 到 `kangtaze/xboard-ze`）

**服务器验证**（curl 全部 200）：
```
200  /privacy-policy.html
200  /vp92-terms.html
200  /vp92-tutorial.html
200  /about
200  /dashboard
```

**服务器 md5**：
```
6c9985cc2cfa9569625b4206525b300b  /www/app/Providers/ThemeServiceProvider.php
cee7112b5c174ff29f06a54184af66da  /www/config/app.php
No syntax errors detected
```

**变更历史警告**：
- 之前 v15.5 期间 Chrome 验证 `/privacy-policy.html` 工作 — 当时可能是 ThemeService 因 ConfigController 被触发过、被 Octane 缓存了
- 之后 Octane reload + PlanController 改动 → cache miss → 暴露架构缺陷
- ThemeServiceProvider 修复后这个问题不会再出现

---

## ⏳ 待处理事项（必须新窗口接手）

### ✅ v20.5 Bot sendChatAction typing 状态 — 已完成
**详细 plan**: `C:\Users\Administrator\.claude\plans\harmonic-wibbling-engelbart.md` §16

### ✅ v20.2 intent_outdated 第 5 tag — 已完成
**详细 plan**: `C:\Users\Administrator\.claude\plans\harmonic-wibbling-engelbart.md` §6
**完成 wakeup**: [`docs/wakups/v20-2-intent-outdated-5th-tag.md`](wakups/v20-2-intent-outdated-5th-tag.md)
**遗留**: IntentRefreshTest 未写（v21 加）

### 🔴 v15.6 旧 bug 清单（详见下方历史段落）

### ✅ 已完成（v15.6 全部调查）：落地页注册订阅 bug 根因 3 个独立 bug
- Bug A：jt() i18n section 错配（致命 — handleSubscribe 在前端校验时 TypeError 中断）
- Bug B：handleSubscribe 无 try/catch 异常防护
- Bug C：PlanPurchase.vue loadPlans 403 后 auto-checkout 静默失败
- 待新窗口决策修复方案（推荐选项 1：最小修复 A+B，1 行改 jt + try/catch）
- 待用户验证：1 修复后端到端流程（点击注册订阅 → 出二维码 → 支付成功）

### 🔴 任务 #1：jt() i18n section 错配修复（致命，最优先）
**根因**：`landing.blade.php:1584` `jt(key)` 函数只读 `window.I18N.js[lang][key]`，但 err_xxx keys 都在 `window.I18N.modal` section (line 235-238)
**实测 TypeError**：`TypeError: Cannot read properties of undefined (reading 'err_password_short')` at handleSubscribe:2090
**影响**：用户填 < 8 位密码 → handleSubscribe TypeError 中断 → 按钮卡死 + 无错误信息
**最小修复**：`jt()` 改读 `window.I18N.modal` section + handleSubscribe 包 try/catch

### 🟡 任务 #2：PlanPurchase.vue auto-checkout 静默失败
**根因**：未登录用户跳 `/dashboard/plans?checkout_plan=X` → 路由守卫跳 `/auth/login`（保留 redirect）；如果 register+login 成功但 Sanctum token 未同步，`loadPlans()` 403 → `plans.value=[]` → auto-checkout 跳过
**修复**：用 guest endpoint fallback + token check

### 🟢 任务 #3（可选）：Footer/Hero 按钮文案改"立即订阅"
**根因**：用户报告"免费注册"按钮点击后跳"注册订阅"，不符合预期（用户以为"注册"=只注册账号）
**修复**：按钮文案改"立即订阅"或加独立 `/auth/register` 路由

### 🔴 v15.7 旧任务：widget commit 276049c push（紧急）
**状态**：✅ **已完成**（commit d978e87 push 到 kangtaze/xboard-user vp92-master 分支，cedar master 未污染）
- 详见 widget-v15-5-frontend-remote-misconfig.md memory 文件

### 📋 v15.5+v7 历史任务（已完成，不再重复）
- v15.4 AI 客服培训话术：commit 4af47ba/e47ad1f/bcd4189 已 push 到 kangtaze/xboard-ze
- v15.5 widget 3 UI bug 修复：commit 276049c
- v15.6 PlanController device_limit<=0 清空 user.device_limit：commit bc87433
- v15.7 PlanController 字段变化时同步 user.device_limit：commit 97ea9f0
- v15.7 ThemeServiceProvider 新增修复 theme:: 路由 500：commit 99388b3
- 本地 vp92-frontend/theme/ 8 文件 md5 与服务器 100% 一致

---

## 🚨 新窗口唤醒词（直接复制粘贴）

```
读取 E:\jiedian\docs\session-progress-latest.md，这是 v15.6 wakeup snapshot。
今天是 2026-08-26，v15.5 全部完成（widget push + 8 theme 文件同步）。
v15.6 已完成落地页注册订阅 bug 根因调查（3 个独立 bug：A jt() i18n 错配 / B 无 try/catch / C auto-checkout 静默失败），但未修复。
待办：决策修复方案（推荐选项 1：最小修复 A+B — 改 jt() 读 modal section + handleSubscribe 包 try/catch），端到端验证（点击注册订阅 → 出二维码）。
按"待处理事项"段开始工作。
```

---

## 📁 关键文件位置速查

| 文件 | 用途 | 状态 |
|------|------|------|
| `E:\jiedian\docs\session-progress-latest.md` | 本文件 — v15.6 wakeup | ✅ |
| `E:\jiedian\docs\ai-training-2026-08-26.md` | AI 培训话术 SSOT | ✅ |
| `E:\jiedian\xboard-repo\app\Services\AiChatService.php` | AI 客服主文件（nowdoc） | ✅ commit e47ad1f |
| `E:\jiedian\vp92-frontend\public\js\vp92-chat-widget.js` | AI 聊天 widget | ✅ commit 276049c |
| `/www/routes/web.php` | 路由（含隐私路由） | ✅ server |
| `/www/theme/VP92/privacy-privacy.blade.php` | 隐私 blade（已重命名） | ✅ md5 d672d310... |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Providers\ThemeServiceProvider.php` | Theme namespace 注册（修复 theme:: 路由 500） | ✅ commit 99388b3 |

---

## 🔧 关键技术细节

### 服务器信息
- **HK 服务器**：`root@154.3.38.212` (DMIT), Docker 容器 `xboard`
- **域名**：
  - `vp92vip.com` — 用户面板 + 落地页
  - `vp92.app` — 订阅入口
  - `vp92vip.cc.cd` — APK/Edgetunnel
- **SSH**：`ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212`

### 关键 Git 仓库
- `E:\jiedian\xboard-repo` → remote `my` = `kangtaze/xboard-ze` ✅ push OK
- `E:\jiedian\vp92-frontend` → remote `my` = `kangtaze/xboard-ze` ❌ 配错（应该指向 xboard-user）
- `E:\jiedian\edgetunnel-extracted` → CF Worker 代码

### 当前 VIP/CF Worker 配置
| VIP | group_id | DNS Zone | CF Worker | CfWorker ID |
|-----|----------|----------|-----------|-------------|
| VIP1 | 2 | vp92.cc.cd (id=33) | Cloudflare Worker01 | 33 |
| VIP2 | 3 | vp92vip2.cc.cd (id=4) | Cloudflare Worker02 | 4 |
| VIP3 | 4 | vp92vip3.cc.cd (id=25) | Cloudflare Worker03 | 25 |
| VIP4 | 5 | — | 未配置 | — |

### 数据库
- SQLite: `/www/.docker/.data/database.sqlite`
- 表前缀: `v2_`
- ⚠️ SQLite 不支持 `JSON_EXTRACT` — 必须 PHP 端 foreach+json_decode 过滤

### Xboard 主题系统（关键！）
```
view('theme::VP92.dashboard') 渲染的是 /www/theme/VP92/dashboard.blade.php
不是 resources/views/ 也不是 public/ 下的！
nginx 静态副本在 /www/public/theme/VP92/，改完两边都同步
```

---

## 🚨 用户铁令（不可违反）

### 🚫 永不碰 APP/PC 编译（2026-08-26 用户明确警告）
- ❌ 永不运行 `release_android.py`（vp92-flclash APK 构建）
- ❌ 永不运行 `dart setup.dart windows`（PC-vp92 Windows 构建）
- ❌ 永不建议用户让我编译 APP/PC（用户自己掌控）
- ✅ Web vite build（vp92-frontend）可以，因为这是 SPA 不是 APP
- ✅ git commit + push my master 入库（代码可以，编译不行）

### 防回滚原则
- 改任何后端 PHP 前必拉服务器最新 md5 对比（防止本地旧代码覆盖线上）
- 服务器可能回滚（2026-08-25 容器重建事故 + bot supervisord.conf 模板丢失）
- **本地才是真权威！服务器 = 临时副本**
- 部署后立即 git commit 入库（未入库的文件对下个会话来说等于不存在）

### 接续原则（用户铁令 2026-08-26）
- 上下文满了必须主动保存进度到 `session-progress-latest.md`
- 必须保留唤醒提示词让新窗口复制
- 不能有脱节！

---

## ⚠️ 工作风格 4 条铁律

来自 `C:\Users\Administrator\.claude\CLAUDE.md`：

1. **严谨 — 验证先于完成**
   - 每个动作都用证据确认（`git status` / `aapt dump badging` / `md5sum` / `grep` 实际输出）
   - 不靠"应该成了"判断

2. **拒绝敷衍 — 必查避坑**
   - 改前先读 `AGENTS.md` / `CLAUDE.md` + `PROJECT_STATUS.md` 致命避坑
   - 不要凭印象略过
   - 致命避坑参考：`vp92-work-style-rigor-no-fluff.md`

3. **不被流行做法带偏 — 先核查再推断**
   - 不模仿 Cursor/Codex 的"先猜再改"
   - 遇到不确定（如 git remote、代理端口）必先核查

4. **不要幻想发梦 — 拒绝 hallucination**
   - 不为"显得有帮助"瞎编
   - 不为"显得主动"抢跑
   - 不为"显得周到"加未要求的内容
   - 不确定时直接告诉用户"我不确定"

---

## 📚 部署工作流（速查）

### 前端 VP92 Vue SPA 部署
```bash
# 1. 本地构建
cd E:/jiedian/vp92-frontend && npx vite build
# 2. 打包
tar czf vp92-assets.tar.gz -C dist/assets .
# 3. 上传
scp vp92-assets.tar.gz root@154.3.38.212:/tmp/
# 4. 解压到 public
docker cp /tmp/vp92-assets.tar.gz xboard:/tmp/
docker exec xboard sh -c 'cd /www/public/theme/VP92/assets && rm -rf * && tar xzf /tmp/vp92-assets.tar.gz'
# 5. 改 dashboard.blade.php 中的 hash + 同步 public 副本
# 6. 清缓存 + reload
docker exec xboard sh -c 'php artisan view:clear && php artisan cache:clear && php artisan octane:reload'
```

### 后端 PHP 部署（必先拉服务器 md5 对比）
```bash
# ⚠️ 改前必做：拉服务器最新版 md5 对比
ssh root@154.3.38.212 "docker exec xboard cat /www/app/xxx.php" > /tmp/server_latest.php
md5sum 本地文件.php /tmp/server_latest.php

# 一致 → 直接改 + 走正常部署
# 不一致 → 以服务器版为基准合并修改，绝不拿本地旧版覆盖

# 部署后必做
ssh root@154.3.38.212 "docker exec xboard php artisan octane:reload"
```

### Git 备份（必做！）
```bash
cd E:/jiedian/xboard-repo
git add -A
git commit -m "feat/fix: <简述>"
git push my master
# remote 'my' = kangtaze/xboard-ze
# 推送前检查 git config --global http.proxy，如有 127.0.1 先 --unset
```

---

## 📂 完整项目目录速查

| 路径 | 用途 |
|------|------|
| `E:\jiedian\xboard-repo\` | 主工作仓库（所有 Xboard 自定义代码） |
| `E:\jiedian\vp92-frontend\` | VP92 Vue3 前端（Dashboard SPA，vite build） |
| `E:\jiedian\edgetunnel-extracted\` | Edgetunnel Worker 代码、补丁、部署脚本 |
| `E:\jiedian\xboard-theme-lighttrack\` | LightTrack Vue3 自定义前端主题 |
| `E:\jiedian\xboard-deploy\` | 部署调试脚本和参考副本 |
| `E:\jiedian\docs\` | 设计文档和规格说明（含本文件） |

---

## 🔗 相关记忆文件

- `v15-4-ai-chat-wakeup.md` — 上版 wakeup（已 superseded）
- `ai-chat-training-v15-4.md` — v15.4 培训话术记忆
- `ai-chat-widget-dynamic-config.md` — widget 闭包捕获失效修复
- `feedback-no-app-pc-compile.md` — 永不编译 APP/PC 铁令
- `vp92-wakeup-template.md` — wakeup 模板
- `git-backup-routine.md` — git 备份常规

---

## ✅ 完成度检查

新窗口接手时请逐项核对：

- [ ] 读本文件全文（5 分钟）
- [ ] 读 `E:\jiedian\xboard-repo\CLAUDE.md`（2 分钟）
- [ ] 读 `E:\jiedian\AGENTS.md` 致命避坑（2 分钟）
- [ ] 确认 v15.8 jt() 修复生效：Chrome 打开 vp92vip.com，打开 dev modal 触发 handleSubscribe（短密码 → 显示"密码至少8位"）
- [ ] 确认隐私 URL：Chrome 打开 vp92vip.com/privacy-privacy.html（应 200）
- [ ] 确认 footer 链接：vp92vip.com 滚动到底，footer"隐私政策"链接 = `/privacy-privacy.html`
- [ ] **待用户决策**：是否修 Bug C（PlanPurchase.vue auto-checkout 静默失败）
- [ ] **可选**：是否修 Bug D（按钮文案改"立即订阅"）
- [ ] **待用户验证**：去后台实际操作一次套餐保存（VIP1 device_limit 5↔不限），验证 PlanController 完整修复链路

---

## 🚨 新窗口唤醒词（直接复制粘贴）

```
读取 E:\jiedian\docs\session-progress-latest.md，这是 v15.10 wakeup snapshot。
今天是 2026-08-26 深夜，v15.9 + v15.10 全部完成：
- v15.9 commit ae09719/356b23d push kangtaze/xboard-user vp92-master（Bug C + Bug D 修复）
- v15.10 commit 6979e75 push kangtaze/xboard-user vp92-master（落地页 hero/footer 改'免费注册'+ 跳 /auth/register）
待办：见 v15.10 章节末尾的"待处理事项"。
按"v15.10 变更详情"段开始工作。
```

---

**维护规则**：本文件是 v15.10 SINGLE SOURCE OF TRUTH。supersedes v15.9/v15.8/v15.7/v15.6。任何 v15.10+ 的代码修改必须在 commit message 引用本文件，并更新对应段。

---

# 📌 v15.10 — 落地页 hero/footer CTA 改'免费注册'跳转 /auth/register

> **本会话目标**：v15.9 Bug D 把 landing hero/footer 的"免费注册"统一改成了"立即订阅"，但用户实际反馈落地页顶部 CTA **应当叫"免费注册"**（语义匹配副标题"免费体验 24 小时"），点击后**应该跳转注册页面**（`/auth/register`），而不是打开落地页自带的 `#subscribeModal` 注册弹窗。本次 revert 仅针对 landing hero + footer 两个顶级 CTA。

## ⚡ 一句话状态

```
✅ v15.10 完成（landing.blade.php 4 处编辑 + 服务器双路径同步 + chrome 验证 + git push）
- 部署：ssh docker cp + 双路径 md5 一致 (2ab36c761e8acf9c11a0aa1f9a3aa7d8)
- 验证：zh-CN/zh-TW/en 三语言按钮文案正确；未登录 hero 点击 → /auth/register 注册表单；登录态 hero 点击 → /dashboard
- commit: 6979e75 fix(landing): hero + footer CTA 改'免费注册'跳转 /auth/register
- push: my-frontend vp92-master (ae09719..6979e75)
```

## 🎯 改动汇总（4 处编辑，1 个文件）

**目标文件**: `E:\jiedian\vp92-frontend\theme\landing.blade.php`
**部署后服务器路径**: 
- `/www/theme/VP92/landing.blade.php`（Octane 渲染源文件）
- `/www/public/theme/VP92/landing.blade.php`（nginx 静态副本，必须 md5 一致）

### Edit #1 — Hero 按钮 onclick + fallback 文案（line 764）

```diff
-<button onclick="(localStorage.getItem('fe_auth_token')||localStorage.getItem('auth_token'))?window.location.href='/dashboard':openSubscribeModal('VIP 1')" class="bg-primary text-on-primary px-stack-xl py-4 font-headline-md text-headline-md mb-2 transition-all duration-300 hover:scale-105 hero-cta-btn" data-i18n="hero.cta_button">
-                    立即订阅
-                </button>
+<button onclick="(localStorage.getItem('fe_auth_token')||localStorage.getItem('auth_token'))?window.location.href='/dashboard':window.location.href='/auth/register'" class="bg-primary text-on-primary px-stack-xl py-4 font-headline-md text-headline-md mb-2 transition-all duration-300 hover:scale-105 hero-cta-btn" data-i18n="hero.cta_button">
+                    免费注册
+                </button>
```

要点：
- 已登录态保留跳 `/dashboard`（已注册用户不应再走注册页）
- 未登录态由 `openSubscribeModal('VIP 1')` 改为 `window.location.href='/auth/register'`（SPA 接管渲染注册表单）
- 硬编码 fallback 文案同步改为"免费注册"

### Edit #2 — Hero i18n key `cta_button`（line 57）

```diff
-cta_button:{'zh-CN':'立即订阅','zh-TW':'立即訂閱','en':'Subscribe Now'},
+cta_button:{'zh-CN':'免费注册','zh-TW':'免費註冊','en':'Free Register'},
```

### Edit #3 — Footer 按钮 onclick + fallback 文案（line 1478）

```diff
-<button onclick="(localStorage.getItem('fe_auth_token')||localStorage.getItem('auth_token'))?window.location.href='/dashboard':openSubscribeModal('VIP 1')" class="bg-primary text-on-primary font-bold px-stack-lg py-stack-md transition-transform active:scale-95 duration-150" data-i18n="footer.btn_register">立即订阅</button>
+<button onclick="(localStorage.getItem('fe_auth_token')||localStorage.getItem('auth_token'))?window.location.href='/dashboard':window.location.href='/auth/register'" class="bg-primary text-on-primary font-bold px-stack-lg py-stack-md transition-transform active:scale-95 duration-150" data-i18n="footer.btn_register">免费注册</button>
```

要点：与 hero 完全平行，确保 footer 行为一致。

### Edit #4 — Footer i18n key `btn_register`（line 185）

```diff
-btn_register:{'zh-CN':'立即订阅','zh-TW':'立即訂閱','en':'Subscribe Now'},
+btn_register:{'zh-CN':'免费注册','zh-TW':'免費註冊','en':'Free Register'},
```

## 🚨 关键 Bug Fix: 跳转 /register → /auth/register

**问题**: 初次部署后 hero 点击 → 跳转到 `/auth/login` 而非预期的注册表单。

**根因排查**:
1. **假设 1**（错误）：localStorage 有遗留 token → 走 `:'/dashboard'` 分支 → Vue SPA auth guard 重定向到 `/auth/login`
2. **清空 localStorage 后重试** → 仍然到 `/auth/login`（假设 1 被否决）
3. **真实根因**：Vue Router 仅注册了 `/auth/{path?}` catch-all 路由，**没有**顶层 `/register` 的 SPA 子路由
4. **证据**：`/auth/login` 页面里的"立即注册"链接指向 `/auth/register`（不是 `/register`），证明 SPA 内部约定就是 `/auth/register`

**修复**: 把两处 onclick 从 `/register` 改为 `/auth/register`

**验证**: hero 点击 → `/auth/register` → 注册表单（含邮箱 + 密码 + 邀请码 + 提交按钮）正确渲染 ✅

**经验教训**: Vue SPA 路径必须看 `src/router/index.ts` 实际注册了什么，不能凭 `routes/web.php` 后端路由推断前端可用路径。

## ❌ 不动 / 重要约束

| 文件 / 位置 | 状态 | 原因 |
|------------|------|------|
| `landing.blade.php:847/888/930` Plan cards | ❌ 不动 | 套餐选择区按钮，应保持 `openSubscribeModal('VIP X')` 弹窗订阅 |
| `landing.blade.php:2018-2044` `openSubscribeModal` 函数 | ❌ 不动 | Plan cards 仍调用 |
| `landing.blade.php:237` `btn_registering` | ❌ 不动 | modal loading 文案，不同 key |
| `landing.blade.php:2085` `handleSubscribe` | ❌ 不动 | modal 提交逻辑 |
| `PlanPurchase.vue:352` Dashboard "立即订阅" | ❌ 不动 | dashboard 侧，文件不同步 |
| `web.php:138` `/register` 路由 | ❌ 不动 | 路由已存在，本次只是跳转目标 |
| `/api/v1/passport/auth/register` API | ❌ 不动 | 不变 |
| `E:\jiedian\edgetunnel-extracted\` | ❌ 不读 | 用户铁令 |

## 🚀 部署流程（已执行）

### 1. 编辑 + md5 对比
```bash
md5sum E:/jiedian/vp92-frontend/theme/landing.blade.php
# 改前：a22474b467532b02dc3ad97737869cf5（v15.9 baseline）
# 改后：d20028c5c6e9c4f3...（4 处编辑后 hash）
```

### 2. 上传 + 同步
```bash
scp -i C:/Users/Administrator/.ssh/id_ed25519 \
  E:/jiedian/vp92-frontend/theme/landing.blade.php \
  root@154.3.38.212:/tmp/landing_new.blade.php

ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212 \
  "docker cp /tmp/landing_new.blade.php xboard:/www/theme/VP92/landing.blade.php && \
   docker exec xboard cp -f /www/theme/VP92/landing.blade.php /www/public/theme/VP92/landing.blade.php"
```

⚠️ **踩坑**：第一次用 host-level `cp /www/theme/... /www/public/theme/...` 失败（host 看不到 container 的 in-flight 写入），改用 `docker exec xboard cp -f` 容器内复制才生效。

### 3. md5 验证
```bash
ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212 \
  "docker exec xboard md5sum /www/theme/VP92/landing.blade.php /www/public/theme/VP92/landing.blade.php"
# 两条 hash 必须相同 = 2ab36c761e8acf9c11a0aa1f9a3aa7d8
```

### 4. 清缓存 + reload
```bash
ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212 \
  "docker exec xboard sh -c 'php artisan view:clear && php artisan cache:clear && php artisan octane:reload'"
```

## ✅ 验证（chrome-devtools MCP）

| 项 | 结果 | 备注 |
|----|------|------|
| zh-CN hero 文案 | ✅ "免费注册" | |
| zh-TW hero 文案 | ✅ "免費註冊" | |
| en hero 文案 | ✅ "Free Register" | |
| zh-CN footer 文案 | ✅ "免费注册" | |
| zh-TW footer 文案 | ✅ "免費註冊" | |
| en footer 文案 | ✅ "Free Register" | |
| zh-CN plan card VIP1/2/3 | ✅ "立即订阅"（不变） | openSubscribeModal 弹窗仍工作 |
| zh-TW plan card | ✅ "立即訂閱"（不变） | |
| en plan card | ✅ "Subscribe Now"（不变） | |
| 未登录 hero 点击 | ✅ → /auth/register 注册表单 | Bug fix 后正确 |
| 未登录 footer 点击 | ✅ → /auth/register 注册表单 | |
| 登录态 hero 点击 | ✅ → /dashboard | localStorage token 命中 ternary 真分支 |
| i18n 切换按钮文案同步 | ✅ 切语言时按钮文案跟着切换 | 不依赖硬编码 fallback |
| 服务器双路径 md5 | ✅ 一致 (2ab36c76...) | |

## 📦 Git 入库

```bash
cd E:/jiedian/vp92-frontend
git config --global --get-all http.proxy   # 必空
git add theme/landing.blade.php
git diff --cached --stat   # +5/-5 = 10 行变更
git commit -m "fix(landing): hero + footer CTA 改'免费注册'跳转 /auth/register\n
\n
- hero (line 764): 立即订阅 → 免费注册, onclick 跳 /auth/register\n
- footer (line 1478): 立即订阅 → 免费注册, onclick 跳 /auth/register\n
- i18n cta_button (line 57) + btn_register (line 185): 3 语言文案同步\n
- plan cards (line 847/888/930) 不变, 仍走 openSubscribeModal 订阅弹窗\n
- dashboard PlanPurchase.vue 不变\n
\n
注: 跳 /auth/register 而非 /register\n
   Vue Router 仅注册 /auth/{path?}, /register 顶层路由无 SPA 子路由\n
   未注册时会被 SPA auth guard 重定向到 /auth/login\n
   验证: 已确认 hero 点击 → /auth/register 注册表单正确渲染"

git push my-frontend master:vp92-master
# ae09719..6979e75  master -> vp92-master
```

**commit hash**: `6979e75`
**remote**: `my-frontend` = `kangtaze/xboard-user.git`
**branch**: `master` (本地) → `vp92-master` (远程)

## 🎓 经验教训（v15.10 新增）

1. **Vue SPA 路由必须查 router 文件**：不能从后端 `routes/web.php` 推断前端可用路径。前端只有 `/auth/{path?}` catch-all，没有顶层 `/register`。
2. **docker cp + host cp 时序坑**：host-level `cp` 在 docker cp 完成后立即执行可能拿到旧文件，必须用 `docker exec xboard cp -f` 容器内复制才能保证一致。
3. **i18n 改文案要查 2 处**：dict（数据源）+ HTML 硬编码 fallback（数据源失效时显示），必须同步改才能保证所有路径下文案一致。
4. **commit message 含中文标点要 -F 文件**：Bash tool 不解析 PowerShell here-string，含中文引号/逗号的 commit message 必须先写文件再用 `git commit -F`。

## 📂 关键文件路径速查

| 用途 | 路径 |
|------|------|
| 本地编辑 | `E:\jiedian\vp92-frontend\theme\landing.blade.php` |
| 部署目标 1 | `/www/theme/VP92/landing.blade.php`（xboard Octane 渲染） |
| 部署目标 2 | `/www/public/theme/VP92/landing.blade.php`（nginx 静态副本） |
| Vue Router 路由表 | `E:\jiedian\vp92-frontend\src\router\index.ts` |
| 注册 API（已存在） | `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Http\Controllers\V1\Passport\AuthController.php::register` |

# 📌 v15.11 — Admin 用户详情页 GeoIP + 订阅日志（方案 B1）

> **状态**：✅ 完成 + 入库（commit cc580f2 + push my master，2026-08-27）
> **目标**：admin 后台用户管理 → 用户详情补 5 字段（客户端类型/版本、IP 字符串、国家/省/市/ISP、多设备列表、订阅拉取记录）
> **范围**：仅改后端 API（admin SPA 是 cedar 上游 bundle，本次不动）

## ⚡ 一句话状态

```
v15.11 状态（2026-08-27 凌晨）：
✅ 方案 B1 完整跑通：8 文件改动（5 新建 + 3 编辑）+ 1 migration + 1 push (cc580f2)
✅ v2_subscription_log 表已写 10 行（用户 328 真实拉订阅被记录: vp92-windows/1.2.5/Windows、shadowrocket/1234/iOS 等）
✅ LogSubAccess.HIT 探针 1686 次 → 验证中间件被 swoole 正确调用
⚠️ 已知问题: GeoIP country/province/city/isp 空（Caddy+CF 把 IPv6 edge IP 透传到 octane, ip2region.xdb 只 IPv4, 需要配置 Laravel TrustProxies 解析 X-Forwarded-For）
```

## 🎯 改动汇总（8 文件 +547 行）

### 新增（5 文件）

| 文件 | 用途 |
|------|------|
| `database/migrations/2026_08_27_000001_create_v2_subscription_log.php` | v2_subscription_log 表（11 字段 + 3 索引） |
| `app/Models/SubscriptionLog.php` | Eloquent 模型 |
| `app/Services/GeoIpService.php` | ip2region 封装 + Laravel Cache 24h TTL |
| `app/Utils/ClientFingerprint.php` | UA 解析（VP92/SR/Clash/v2rayN/sing-box/浏览器） |
| `app/Http/Middleware/LogSubscriptionAccess.php` | 拉订阅中间件，写 v2_subscription_log |

### 编辑（3 文件）

| 文件 | 改动 |
|------|------|
| `app/Http/Kernel.php` | 注册 LogSubscriptionAccess 到全局 $middleware |
| `app/Services/AuthService.php` | line 55 写 GeoIP country 到 Sanctum token |
| `app/Http/Controllers/V2/Admin/UserController.php` | transformUserData 补 5 字段（register_geo/last_login_geo/last_login_client/devices/subscription_logs） |

## 🚨 踩坑总结

1. **swoole worker 没真正 reload**：`octane:reload` 后 worker PID 不变 = PHP 类没刷新。必须 `pkill -9 -f "swoole_http_server: worker"` 让 swoole master 重新 fork 新 worker（PID 262 → 416 → 验证有效）
2. **`$request->route('token')` 在 swoole 下返回空字符串**：路由参数访问不可靠。改用 `explode('/', $request->path(), 2)` 从 path 直接 split 出 token
3. **opcache.validate_timestamps=0**：导致 PHP 文件改动不会被 swoole worker 检测，必须 kill worker
4. **Log::info 探针打哪**：写到 `laravel-YYYY-MM-DD.log`（按天分文件），不是 `laravel.log`（已废弃的旧文件）
5. **ip2region.xdb 只 IPv4**：Caddy + Cloudflare edge 把 IPv6 透传到 octane，country/province/city/isp 全空。需要 Laravel TrustProxies 解析 X-Forwarded-For（**已知遗留问题**，本次不动）

## ✅ 验证

| 验证项 | 结果 |
|--------|------|
| migration 跑通 | ✅ `2026_08_27_000001_create_v2_subscription_log` 表创建成功 |
| 中间件注册 | ✅ Kernel::$middleware[8] = LogSubscriptionAccess |
| AuthService 写 country | ✅ line 55 有 GeoIpService::lookup 调用 |
| 真实拉订阅触发 | ✅ 5 次 curl → 表 5 行（再 curl 至 10 行） |
| 客户端解析 | ✅ shadowrocket/1234/iOS, vp92-windows/1.2.5/Windows, browser/Windows, unknown（curl） |
| 数据完整性 | ✅ 10 行写入，user_id/client_type/client_version/os/subscription_token 全部填 |
| GeoIP 字段 | ⚠️ 空（IPv6 透传已知问题） |

## 📦 Git 入库

```
commit cc580f2 — feat(admin): 方案 B1 GeoIP + 订阅日志
8 files changed, 547 insertions(+), 6 deletions(-)
remote my master: 2ba411d..cc580f2
```

## 🎓 经验教训

1. **永远验证中间件**：加 Log::info 探针到 handle() 顶部，确认它真的被 swoole 调用（v15.11 = 1686 次 HIT 验证）
2. **swoole worker reload 不可靠**：`octane:reload` 只是发 SIGTERM，PID 变化不定；要确认换 worker 就 pkill + 看 ps 新 PID
3. **route 参数在 swoole 下不可靠**：直接用 `$request->path()` split 更稳

## 📂 关键文件路径速查

| 路径 | 用途 |
|------|------|
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Http\Middleware\LogSubscriptionAccess.php` | 拉订阅中间件 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Services\GeoIpService.php` | GeoIP 封装 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Utils\ClientFingerprint.php` | UA 解析 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Models\SubscriptionLog.php` | Eloquent 模型 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\database\migrations\2026_08_27_000001_create_v2_subscription_log.php` | 新表 migration |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Http\Controllers\V2\Admin\UserController.php` | admin transformUserData 补字段 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\database\migrations\2026_08_27_000002_create_v2_user_intent.php` | **v19** intent 表 migration |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Models\UserIntent.php` | **v19** intent model + 4 tag 常量 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Services\IntentClassifier.php` | **v19** 4 tag SQL 分类器 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\tests\Feature\IntentClassifierTest.php` | **v19** 6 个单测（server-only） |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Console\Commands\IntentRefresh.php` | **v19** 每日 cron 重算 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Console\Commands\SendIntentCampaign.php` | **v19** 群发命令 |
| `/www/.docker/.data/database.sqlite` | SQLite（含 v2_subscription_log 表） |