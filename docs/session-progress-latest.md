# VP92 项目进度 — 2026-08-25 v9 ✅ **完成+入库** + v10 后台用户信息缺失排查中

## 🚨 唤醒提示词（直接复制粘贴到新窗口）

```
读取 E:\jiedian\docs\session-progress-latest.md，这是 VP92 Xboard 项目最新进度。
这是 2026-08-25 v9 快照（v9 完成：EdgetunnelServiceProvider 注册修复 + composer PSR-4 + direct_ip_mode UI + commit fb13f0a）。
从 "## 当前状态" 开始，按 "## 待办清单" 顺序接手。
所有 ssh 命令加 -i C:/Users/Administrator/.ssh/id_ed25519。
CF Worker 代码(E:\jiedian\edgetunnel-extracted)绝对不要读取。
完整流程链路: 用户 → Xboard(/s/{token}) → Hook(client.subscribe.servers) → EdgetunnelServiceProvider → DB 缓存 → 注入订阅。
改任何后端 PHP 前先拉服务器最新版本对比 md5，避免覆盖。
git push my 前需要临时设 git proxy http://127.0.0.1:10808（路由透明代理对github.com无效）。
**新上下文无记忆**: 关键架构 + 根因 + 记忆文件已存入 memory/Xboard架构.md / missing-edgetunnel-provider.md 等。
核心已解问题: VIP1 订阅不可用 = EdgetunnelServiceProvider 未注册 → 已修。
进行中: 后台用户列表缺注册IP/登录IP/设备显示（cedar2025 SPA 显示问题）→ 见 v9 待办。
```

---

## 🎯 当前状态：v9 全部完成+入库 ✅ | v10 后台用户信息缺失 🔍

### ✅ v9 已完成（commit fb13f0a push kangtaze/xboard-ze，4 files +27/-3）
- **EdgetunnelServiceProvider 注册修复**：config/app.php 加 `XboardFerrari\Providers\EdgetunnelServiceProvider::class`（在 OctaneServiceProvider 之后）
- **composer.json PSR-4 补**：本地加 `"XboardFerrari\\": "app/XboardFerrari/"`（服务器原本就有，本地漏同步）
- **VIP1 订阅恢复**：122 节点重新注入用户订阅 ✅ 用户确认可用
- **direct_ip_mode UI 部署**：edgetunnel.blade.php 4 处 + EdgetunnelController.php 3 处

### ⚠️ v10 进行中（用户反馈）
- **后台看不到用户设备、注册 IP、登录 IP**（以前有这些功能）
- 已确认：UserController.fetch() 返回 `v2_user.*`（含 register_ip, last_login_ip, last_login_user_agent）✅
- 已确认：cedar2025 SPA 显示用户信息页 → umi.js 中无 IP/device 字段引用
- **猜测根因**：SPA 显示字段丢失，需要在 Xboard 端添加 Hook 注入 device 解析

---

## 📋 待办清单（按优先级）

### 🔥 P0 v10：后台用户信息缺失修复
1. **查 cedar2025 SPA 用户列表页**：SPA 调 `/api/v2/{secure_path}/user/fetch`（AdminRoute line 133）→ V2\Admin\UserController::fetch → 返 `v2_user.*`
2. **注册 `admin.user.transform` Hook**：在 `XboardFerrari\Providers\UserInfoProvider` 中加 Hook 解析 `last_login_user_agent` → `device` 字段（iPhone/Android/Windows/Mac/Linux）
3. **若 SPA 真不显示 IP**：在 transform 中显式注入 `register_ip`, `last_login_ip`, `last_login_user_agent` 字段（实际已有，但确认别名映射）

### 💡 P1 v10 独立
4. **修复 vp92vip.cc.cd R2 bucket** — 登 CF 控制台重传 APK（v8 老问题）
5. **重写 renewalMarketing / maintenanceVip4 邮件模板**（官方风格）
6. **把 3 个 Bot 加进 supervisord conf** — telegram-ai / admin-ai / kawu-ai

### ⚠️ P2 优化
7. **SPA 忽略 ?redirect= query param** — 跳默认 dashboard

---

## 🧠 v9 根因发现（必读，避免重复踩坑）

### EdgetunnelServiceProvider 注册缺失 BUG
- **症状**：所有用户订阅拿不到 Edgetunnel 节点；VIP1 因无 fallback v2_server 完全空
- **根因**：Provider 文件存在 `/www/app/XboardFerrari/Providers/EdgetunnelServiceProvider.php`，但**未在 `config/app.php` providers 数组中注册**
- **后果**：`boot()` 永不行 → `HookManager::registerFilter('client.subscribe.servers', ...)` 永不调用 → 订阅跳过 EdgetunnelServiceProvider hook
- **修复**：commit fb13f0a + octane:reload 后无 "Class not found" 错误

### composer.json XboardFerrari PSR-4 缺失
- 服务器 composer.json 有 `"XboardFerrari\\": "app/XboardFerrari/"` 
- **本地仓库曾缺这一行**（历史漏同步）
- 修复：commit fb13f0a 同步本地

### Edgetunnel 数据流（用户关键提示）
> "肯定是缓存在 Xboard 上啊，Xboard给用户订阅子节点啊，不可能给上游地址的啊"

```
1. 用户订阅 → Xboard ClientController@subscribe(/s/{token})
2. HookManager::filter('client.subscribe.servers', $servers, $user, $request)
3. → EdgetunnelServiceProvider 回调（priority=10）
4. → EdgetunnelService::getAvailableNodes($user) → DB 查询
5. → 注入子域名（sg01.vp92.cc.cd）→ 不是上游 IP
```

### 关键发现
- **CF Worker KV 只存 users，不存 nodes**（sync:all-workers-kv 推 user-list + user-map）
- **Worker 通过 Xboard API 实时拉 nodes**（但具体机制待验证）
- **v2_edgetunnel_node** 是缓存节点（name, host, port, uuid, path, sni 等）

---

## 📊 提交记录

| Commit | 说明 |
|--------|------|
| **fb13f0a** | ✅ fix: 注册 EdgetunnelServiceProvider + 直连端口选择 UI + composer PSR-4 补 |
| a626b93 | feat: SPA token bridge 替代 login.blade.php + 修 Bearer 前缀 BUG |
| 6b883ab | feat: 备份 Bot 命令文件 |
| 1bb5fc7 | fix: Caddyfile 加 @admin rewrite 恢复 /2d532679/edgetunnel 和 /cf-worker |
| f8e6c81 | feat: send:update-notice |

最近 push: kangtaze/xboard-ze.git master (2026-08-25 18:50+)

---

## 🌐 域名速查
| 域名 | 用途 | 状态 |
|------|------|------|
| vp92vip.com | 用户面板/落地页 + Xboard API | ✅ |
| vp92vip.com/2d532679 | cedar2025 admin SPA | ✅ |
| vp92vip.com/2d532679/edgetunnel | Edgetunnel Blade | ✅ |
| vp92vip.com/2d532679/cf-worker | CF Worker Blade | ✅ |
| vp92.app | 订阅入口 | ✅ |
| vp92vip.cc.cd | APK + R2 (404 待修) | ❌ |
| vp92.cc.cd | CF Worker01 (VIP1) | ✅ 已部署 id=46 |

---

## 📂 项目目录结构（每个文件的作用）

### E:\jiedian\ 根目录
```
├── CLAUDE.md                    # 项目说明（新会话必读）
├── docs/                        # 设计文档和进度
│   └── session-progress-latest.md  # 当前进度（**必读**）
├── xboard-repo/                 # ★ 主工作仓库
│   ├── app/                     #  自定义代码（XboardFerrari 命名空间）
│   ├── XboardFerrari/           #  镜像副本（早期版本同步用）
│   ├── resources/views/admin/   #  自定义后台 Blade
│   └── Xboard/Xboard-master/    #  上游 Xboard 参考
├── edgetunnel-extracted/        # CF Worker 代码 + 部署脚本（**禁止读取**）
├── xboard-theme-lighttrack/     # 旧前端主题
├── xboard-deploy/               # 部署调试副本
└── docs/                        # 设计文档
```

### E:\jiedian\xboard-repo\ 自定义文件清单（详见 docs/文件结构说明.md）

#### `app/XboardFerrari/`
- **Models/EdgetunnelSubscription.php** — Edgetunnel 订阅 Eloquent 模型（含 dnsWorker 关系、group_ids cast 为 array）
- **Providers/EdgetunnelServiceProvider.php** — 注册 client.subscribe.servers Hook 注入节点（**必须在 config/app.php 注册**！）

#### `app/XboardFerrari/`（app/ 同级，镜像副本）
- **Http/Controllers/Admin/EdgetunnelController.php** — Edgetunnel 后台管理 API（订阅/节点/用户/服务器 CRUD）
- **Models/EdgetunnelNode.php** — 节点 Eloquent 模型
- **Models/EdgetunnelSubscription.php** — 订阅模型副本
- **Providers/EdgetunnelServiceProvider.php** — Provider 副本
- **Services/EdgetunnelService.php** — 核心服务（syncSubscription 拉上游、getAvailableNodes 按组筛、testLatency 延迟）
- **routes/admin.php** — 自定义后台路由（edgetunnel/* + cf-worker/*）

#### `app/Http/Routes/`
- **V1/UserRoute.php** — 用户 API 路由（user/order/gift-card/ai/knowledge 等）
- **V2/AdminRoute.php** — 后台 API 路由（含 cf-worker + edgetunnel 路由 + bridge 页面）

#### `app/Http/Controllers/V1/`
- **User/AiController.php** — 登录用户 AI 客服（带个人上下文）
- **Guest/GuestAiController.php** — 访客 AI 客服（无个人上下文）

#### `app/Services/`
- **AiChatService.php** — AI 客服核心（DeepSeek API）
- **AuthService.php** — Sanctum auth + token 管理
- **Auth/LoginService.php** — 登录业务逻辑

#### `app/Console/Commands/`
- **TelegramAiPoll.php** — 客服 Bot（用户侧）
- **TelegramAdminAiPoll.php** — 管理 Bot（管理员侧）
- **TelegramKawuPoll.php** — 卡务 Bot（礼品卡）
- **SendPromotionMail.php** — 营销邮件群发
- **SendRemindMail.php** — 续费提醒邮件
- **SendRenewalCampaign.php** — 续费活动邮件
- **SendUpdateNotice.php** — 版本更新邮件

#### `resources/views/admin/`
- **bridge.blade.php** — SPA token 桥接页（无 Sanctum 时跳转登录）
- **cf-worker.blade.php** — CF Worker 管理 Blade
- **edgetunnel.blade.php** — Edgetunnel 订阅管理 Blade（直接渲染版 admin page）

#### `Xboard/Xboard-master/app/Http/Controllers/V2/Admin/`
- **UserController.php** — 后台用户管理（fetch/update/dumpCSV/sendMail/ban/destroy 等）
- **CfWorkerController.php** — CF Worker 后台 API（list/save/deploy/delete/redeploy）
- 其余 ConfigController / CouponController / GiftCardController / KnowledgeController / MailTemplateController / NoticeController / OrderController / PaymentController / PlanController / PluginController / StatController / SystemController / ThemeController / TicketController / TrafficResetController / Server/* — 上游 Xboard 控制器

#### `Xboard/Xboard-master/app/Http/Controllers/V1/Client/`
- **ClientController.php** — 用户订阅端点（/s/{token} → 调 Hook → 输出订阅）
- **AppController.php** — Dashboard 应用版本/下载信息

### E:\jiedian\edgetunnel-extracted\（禁止读取）
- **patched-worker.js** — 主部署 Worker（修改版）
- **wrangler.jsonc** — Wrangler 配置
- **kv-sync-v4.sh** — KV 同步脚本
- **dns-sync.py** — DNS 同步
- *.py — 各种诊断 + 部署脚本（不要碰）

---

## 🔧 SSH 操作技巧

### 容器内执行（关键！）
```bash
# 1. 文件先 scp 到服务器 /tmp/
scp -i C:/Users/Administrator/.ssh/id_ed25519 local.txt root@154.3.38.212:/tmp/local.txt

# 2. docker cp 进容器（容器内 /tmp 看不到宿主的）
ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212 \
  "docker cp /tmp/local.txt xboard:/tmp/local.txt"

# 3. 在容器内执行
ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212 \
  "docker exec xboard sqlite3 /www/.docker/.data/database.sqlite < /tmp/local.txt"
```

### PowerShell 转义陷阱
- 单引号 + 双引号 + $(date) → PowerShell 会尝试解释
- 解决：写 `.sh` 文件 → scp 到 /tmp/ → `bash /tmp/file.sh`
- 或用全路径 Python：`/c/Users/Administrator/AppData/Local/Programs/Python/Python313/python.exe -c "..."`

### 部署后必做
```bash
ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212 \
  "docker exec xboard php artisan octane:reload"
```

---

## 🔗 相关 memory 文件（v9 新增）

- [[xboard-architecture-overview]] — **Xboard 完整架构**（theme/Hook/Provider/Controllers/数据流）
- [[edgetunnel-architecture]] — **Edgetunnel 完整数据流 + 缓存位置 + 配置**
- [[missing-edgetunnel-provider]] — **EdgetunnelServiceProvider 注册缺失 BUG**（v9 修复）
- [[composer-xboardferrari-psr4-missing]] — composer PSR-4 缺失 BUG（v9 修复）
- [[session-2026-08-25-v8-vip1-debug]] — v8 快照（旧）
- [[session-2026-08-25-v6-admin-login-blade]] — v7 完成
- [[container-recreate-incident-2026-08-25]] — 容器重建事故
- [[xboard-caddyfile-config-location]] — Caddyfile 双份位置
- [[cf-worker-500-missing-blade]] — cf-worker 500 根因
- [[login-blade-auth-data-bug]] — data.data.token vs auth_data
- [[admin-spa-login-vs-login-blade]] — 用户方向争议
- [[admin-login-redirect-loop]] — _t query 方案

---

## ⚠️ v9 → v10 关键交接

**v10 接手第一件事**：
1. 读 `docs/session-progress-latest.md` ← 这个文件
2. 读 `docs/文件结构说明.md` ← 新建的文件清单
3. 读 memory/xboard-architecture-overview.md + missing-edgetunnel-provider.md + edgetunnel-architecture.md
4. 从 P0 v10 "后台用户信息缺失修复" 开始

**v10 不要做的事**：
- ❌ 不要重做 v9 已修的 VIP1 订阅（已 commit fb13f0a）
- ❌ 不要读 E:\jiedian\edgetunnel-extracted 下的 .js/.py 文件
- ❌ 不要修改上游 Xboard/Xboard-master/ 核心 Controller

**v10 已知风险**：
- 容器重建事故恢复后状态可能漂移 → 改任何 PHP 前先 `md5sum` 对比
- 直连透明代理 github.com 不可达 → push 前设 `git config --global http.proxy http://127.0.0.1:10808`，push 后 unset
- Octane 重启只用 `reload` 不要 `start`（会断连）