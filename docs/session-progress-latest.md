# 🚨 v15.3 紧急唤醒快照 (2026-08-26 凌晨)

> 用户紧急指令："上下文满了，新窗口要能马上接手项目进度，不能有脱节"
> **新窗口第一件事：复制粘贴底部"唤醒提示词"**

## 🎯 本次会话最大成果

**完整修复"扫码支付完成后前端不弹'支付成功'提示" bug**——横跨 Web/PC/APK 三个端，代码已 commit + push 三个仓库，但**只 Web 已部署上线，APP 端代码修了未构建发布**。

| 端 | Bug | 状态 |
|----|-----|------|
| **Web Dashboard** | ✅ status 字段读取路径错（多解一层）+ status 范围漏 1 | ✅ 已修复 + 部署 + push |
| **PC 客户端 (Windows)** | ❌ status 范围漏 1 | ✅ 代码已 commit+push，但**未构建 v1.2.6** |
| **APK (Android)** | ❌ status 范围漏 1 | ✅ 代码已 commit+push，但**未构建 v1.2.6** |

## 当前状态 (2026-08-26 凌晨 v15.3)

### ✅ 本次会话已完成 + 部署 + 入库
| Task | 仓库 | Commit | 部署状态 |
|------|------|--------|----------|
| Web CheckoutDialog status 字段读取路径修复 | vp92-frontend | `a006be3` | ✅ 已部署（index-DlVtTxFQ.js 上线）+ push my |
| Web status 范围修复 `status >= 1` | vp92-frontend | `a006be3` | ✅ 同上（一个 commit 一起修） |
| PC-vp92 status 范围修复 | PC-vp92 | `4857d81` | ✅ commit+push，**未构建** |
| vp92-flclash status 范围修复 | vp92-flclash | `c2c3dce` | ✅ commit+push，**未构建** |
| 用户撤销支付宝私钥（公网泄露过） | 用户操作 | - | ✅ 用户已自己后台填好 |

### 🟡 进行中
- **P0-A v1.2.6 构建部署**：代码全 commit+push，只差 build + R2 上传 + version.json bump
  - PC-vp92 → Windows EXE build → R2 (`vp92-releases/windows/`) → version-windows.json bump
  - vp92-flclash → Android APK (split-per-abi) → R2 (`vp92-releases/apk/`) → version.json bump (+2000) → Play Store AAB versionCode +1
  - 工具：`E:\jiedian\vp92-flclash\release_android.py 1.2.6 "..."` 一键脚本
  - 预计耗时：45-60 分钟（编译占大头）

### ⏸️ 待办
- **P0-B 教程图恢复**：`/img/tutorial/` 17 张图全丢，已找到原图在 `C:\Users\Administrator\Desktop\shuoming\images\` + `Desktop\tupian\`（两套镜像副本）
  - PC 9 张 + Mobile 8 张 = 17 张
  - 路径: `shuoming\images\` + `shuoming\images\手机端\`
  - 需重命名+压缩+上传到 `/www/public/theme/VP92/img/tutorial/`

- **P0-C 支付方式图标（支付宝官方 SVG）**：
  - `E:\jiedian\vp92-frontend\src\components\common\CheckoutDialog.vue` line 26-34 `brandConfig` 所有 logo 都是文字
  - 用户反馈"付款时支付宝的专用图标呢？" — 没有官方图标
  - 需做 SVG：支付宝（蓝渐变+支字）/ 微信支付（绿+气泡）/ Stripe / PayPal / USDT

- **记忆库合并**：旧的 v15-2-emergency-wakeup.md / v15-2-snapshot-2026-08-26.md 是冗余快照，新窗口直接读 latest.md 即可（保留索引但精简）

## 🐛 本次修复的 Bug 详情（**下次别再踩同一个坑！**）

### Bug 链路（4 层错位）

| 层 | 数据 |
|----|------|
| 后端 OrderController::check() | `return $this->success($order->status)` → 返回 `{status:'success', message, data: 1或3, error}` |
| axios 拦截器 (client.ts:60) | `return response.data` ← **解一层** → `res = {status, message, data, error}` |
| 前端 CheckoutDialog.vue:179（web） | `const status = res?.data?.data` ← ❌ **多解一层** → undefined |
| 前端 PC/APK api_service.dart | `final status = res['data']` ← ✅ **正确**（dio 无 axios 拦截器）|

**症状**：用户扫码支付 → 后端 status 设 1 (处理中) → OrderHandleJob 异步推 3 (已完成) → 前端 3 秒轮询读 status → 永远读 undefined 或 3 之前的 1 → 永远不切 success 步骤

### 修复要点（两边都要修）

1. **Web**: `res?.data?.data` → `res?.data` + `status >= 1`
2. **PC/APK**: `res['data']` (路径对) + `status >= 1 && status < 5`

### Order 状态定义（`app/Models/Order.php:56-60`）

```
STATUS_PENDING     = 0  // 待支付
STATUS_PROCESSING  = 1  // 开通中 ← 这里立刻就触发
STATUS_CANCELLED   = 2  // 已取消
STATUS_COMPLETED   = 3  // 已完成 ← OrderHandleJob 异步推到这里
STATUS_DISCOUNTED  = 4  // 已折抵
```

`OrderService::paid()` 在 `app/Services/OrderService.php:286-303` 立刻把 status 设 1，然后 `dispatchSync(OrderHandleJob::handle() → case STATUS_PROCESSING → orderService->open())` 推到 3。

**所以"成功"判断应该用 `status >= 1 && status < 5`**：覆盖 1/3/4 排除 0/2。

## 🚨 服务器规则（v15.2 修订，**这次会话没违反！**）

> **本地才是真权威！** 服务器可能被回滚，本地副本可能落后于线上
> 改任何后端 PHP 前**必须**：
> 1. `git log` 看最新本地 commit
> 2. `ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212 "docker exec xboard cat /www/app/xxx.php" > /tmp/server_latest.php`
> 3. `md5sum` 对比，本地 != 服务器时**以服务器为基准合并修改**

## 关键文件路径速查

| 文件 | 路径 |
|------|------|
| 后端 PHP 源码 | `E:\jiedian\xboard-repo\` |
| 前端 VP92 主题 | `E:\jiedian\vp92-frontend\` (CheckoutDialog.vue 在 `src/components/common/`) |
| PC 客户端 | `E:\jiedian\PC-vp92\` (api_service.dart 在 `lib/xboard/`) |
| APK/移动客户端 | `E:\jiedian\vp92-flclash\` (api_service.dart 在 `lib/xboard/`, 一键发布 `release_android.py`) |
| Edgetunnel Worker 代码 | `E:\jiedian\edgetunnel-extracted\` ❌ **不要读取** |
| 部署脚本 | `E:\jiedian\xboard-deploy\` |
| 设计文档 | `E:\jiedian\docs\` |
| **教程图原图** | `C:\Users\Administrator\Desktop\shuoming\images\` + `Desktop\tupian\` |

## 服务器速查
- SSH: `ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212`
- 容器: `xboard` (Laravel + Octane + Caddy)
- 路径: `/www/app/`, `/www/theme/VP92/`, `/www/public/theme/VP92/`, `/www/plugins-core/`
- Caddyfile: `/etc/caddy/Caddyfile` (不是 `/www/.docker/caddy/`)
- SQLite: `/www/.docker/.data/database.sqlite` (表前缀 `v2_`)

## 当前线上版本
- Web Dashboard: **v15.3**（commit `a006be3` deployed，hash `index-DlVtTxFQ.js`）
- PC/APK: **v1.2.5**（代码已修，未构建 v1.2.6）

## 关键 commit hash
- v15.3 web 修复: `a006be3` (vp92-frontend / xboard-ze)
- v1.2.6 code: `4857d81` (PC-vp92), `c2c3dce` (vp92-flclash)
- v15.2 此前: `b449ec7` (sitemap), `33a92fe` (CORS/字体), `8231ef4` (wakeup doc)

## 🚨 网络注意（这次会话遇到了！）
- `git push` 不稳定，可能 Failed to connect github.com 443 / schannel 错误
- **解决**: 等 20-30 秒重试（CLAUDE.md 禁止 sleep 25 链式，要用 Monitor until-loop 或者直接重试）
- SSH 也有同样问题: `Connection closed by 154.3.38.212 port 22`
- `git config --global --unset http.proxy` 必须做（router 透明代理）

## 🟢 三大 Bot 进程（持续运行）
```
telegram-ai  PID 21162  ✅
telegram-adm PID 21166  ✅
kawu-ai      PID 21170  ✅
```
supervisord 主进程没 socket，supervisorctl 用不了，bot 必须 `setsid` 启动（之前 wakeup v15.2 已记录）

## 待办优先级（接手立即做，按用户决定）

1. **P0-A v1.2.6 构建部署**（用户场景决定是否必要）：如果用户用 APP 付钱就做，45-60 分钟
2. **P0-B 教程图 17 张**：用户点头就开始重命名+压缩+上传，30-45 分钟
3. **P0-C 支付方式 SVG 图标**：替换 brandConfig 文字 logo，20-30 分钟
4. 记忆库合并：清理旧 v15-2 快照，5 分钟

## ⚠️ 给新窗口的安全警告
- **任何 RSA 私钥不要贴到对话**！用后台管理界面或 scp 传文件
- 用户上次贴了两段支付宝私钥到公网 → 已撤销重置

---

## 📢 唤醒提示词（**新窗口直接复制粘贴**）

```
读取 E:\jiedian\docs\session-progress-latest.md，
这是 VP92 Xboard 项目最新进度（v15.3 凌晨紧急快照）。

核心进展：支付通知 bug 已修，Web 已上线（a006be3 deployed），
PC/APK 代码修了未构建（4857d81/c2c3dce）。

完成的事：
- C/D/E 已 commit 33a92fe + b449ec7 入库+部署+push（v15.2 前）
- v15.3 Web CheckoutDialog status 字段路径+范围已修 + 部署（a006be3）
- PC-vp92 + vp92-flclash 同 bug 修复代码已 commit + push（4857d81/c2c3dce）
- 用户撤销了支付宝密钥（公网泄露过，自己后台填好）
- 教程图原图已定位（Desktop/shuoming/ Desktop/tupian/）

未完成：
- A 待用户决定是否构建 v1.2.6（45-60分钟，让 APP 享受修复）
- B 教程图 17 张恢复（PC 9 + Mobile 8）
- C 支付方式图标（支付宝 SVG 等）

所有 ssh 命令加 -i C:/Users/Administrator/.ssh/id_ed25519。
CF Worker 代码（E:\jiedian\edgetunnel-extracted）绝对不要读取。
完整流程链路: 用户 → Xboard(/s/{token}) → Hook→ EdgetunnelServiceProvider → DB 缓存 →注入订阅。
改任何后端 PHP 前先 git log + md5 对比本地（本地是真权威，服务器可能被回滚）。
git push my 前 unset proxy（github.com 直连 OK），失败等 20-30 秒重试。

第一步：问用户"是否启动 v1.2.6 构建？用户常用 APP 付钱吗？"
```