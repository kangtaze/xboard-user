# VP92 项目进度 — 2026-08-24 v3（最新）

## ⚡ 新窗口接手必读：当前有一个进行中的任务

### 📧 任务A：版本更新通知群发邮件（设计已完成大半，待继续）

**用户原始需求（原话要点）**：
> 发一封邮件给所有的客户，告知请下载最新版本客户端，以免导致无法登录的情况发生。
> APP端上可以踢出历史设备记录，如不更新新版本，会导致无法自行删除记录。

**用户已拍板的决定**：
1. **收件人范围**：「封禁用户不发，其余都发」（即 `is_admin=false AND banned=false`，**包含**已到期用户）
2. **发送机制选型**：方案 A —— 新建独立 artisan 命令 `send:update-notice` + 新邮件模板 `updateNotice`
   - 骨架复制 `E:\jiedian\SendRenewalCampaign.php`（chunk 分批 + delay + dry-run 预览 + 进度条 + 失败统计表）
   - 收件人查询去掉 expired-only/cooldown 逻辑（一次性通知，不需要冷却选项）
   - 模板名独立为 `updateNotice`，mail_log 可单独追踪，不与续费类冷却互相干扰
3. **文案框架已给用户看过**（官方风格：深蓝 #0f3460 banner + 红线 #e94560，标杆 `renewalCampaign-v125.blade.php`）：
   - 主题行：「【VP92】重要更新提醒 — 请升级最新版客户端，避免影响正常登录」
   - Banner: VERSION UPDATE · V1.2.5 /「重要版本更新，请及时升级」
   - 01/为什么必须升级：旧版客户端将逐步停止兼容，继续使用**可能无法正常登录**；新版重构连接内核更稳更快
   - 02/新版独有设备管理功能：APP 内查看所有历史登录设备记录 + **一键踢出不再使用的设备**；⚠️ 旧版本无法自行删除历史设备记录
   - 03/如何更新：已安装→APP内 我的→检查更新；未安装→访问 vp92vip.com 下载
   - CTA 按钮：「立即下载最新版」→ https://vp92vip.com

**下一步动作（按顺序）**：
1. 问用户一个悬而未决的小问题：**文案里要不要写具体截止日期**（如"X月X日前完成升级"？）——注意用户不喜欢 AskUserQuestion 弹窗工具，直接在对话里文字问
2. 呈现完整设计稿（文案全文 + 发送方案细节）→ 获用户批准
3. 写新模板 blade（参考 `E:\jiedian\renewalCampaign-v125.blade.php` 结构改内容区块）部署到容器 `/www/resources/views/mail/default/updateNotice.blade.php`
4. 写命令 `SendUpdateNotice.php`：本地写好 → 放三处（`E:\jiedian\`根目录副本 + `xboard-repo/app/Console/Commands/` + 容器内）——参照 SendRenewalCampaign 三处同步约定
5. dry-run 验证收件人数量 → 先发测试邮件到 **kangtazezege@gmail.com**（管理员测试邮箱，勿打错！）→ 用户确认后全量群发
6. 群发姿势：`docker exec -d xboard sh -c 'cd /www && php artisan send:update-notice --force > /tmp/update-notice.log 2>&1'`
7. 结束前 git commit + push my master

---

## ✅ 本次已完成：控制面板「应用下载」对接后台 APP 设置（2026-08-24 下午）

### 问题与根因
- 用户反馈：控制面板里"下载应用"卡片没有跟后台系统设置的 APP 设置对接
- **根因**：前端 Dashboard JS 正确调用 `/api/v1/user/app/getVersion`，控制器 `V1\Client\AppController@getVersion` 也存在且正确读 `admin_setting()`，但 **V1 的 `UserRoute.php` 从未注册这条路由** → 404 → JS catch 后卡片显示空/敬请期待（与上次 `user/ai/chat` 404 同类病根：孤儿控制器方法）
- 后台设置本身正常（v2_settings）：windows/android 均 `VP92极速1.2.5` + `https://vp92vip.cc.cd/windows/VP92-Setup-1.2.5.exe` / `https://vp92vip.cc.cd/apk/vp92-1.2.5.apk`；macos=Shadowrocket 商店链接；ios=NULL 未设置

### 修复内容（已在服务器生效并验证）
1. 容器内原文件已备份：`/www/app/Http/Routes/V1/UserRoute.php.bak-20260824`
2. 在 `// Stat` 段后追加：
```php
// App 版本与下载信息（Dashboard 应用下载卡片）
$router->get('/app/getVersion', [\App\Http\Controllers\V1\Client\AppController::class, 'getVersion']);
```
3. `route:clear && config:clear && octane:reload`
4. **验证**：`route:list` 显示已注册；生成一次性 Sanctum 探针 token 实测 HTTP 返回 success + 全部下载链接数据；探针 token 已删除

### Git
- Commit `28e34f9` feat: V1 user/app/getVersion 路由注册（已 push my master）
- **重要发现**：本地 `xboard-repo` 此前**没有** `app/Http/Routes/` 目录！本次新建了 `xboard-repo/app/Http/Routes/V1/UserRoute.php`（从服务器拷回的修复版）。本地 repo 只存自建/修改过的文件，不是全量镜像——以后改 Routes 类文件记得同步进 repo

### 备注
- iOS 下载卡片实际映射的是 `macos_download_url` 字段（落地页 landing.blade.php 同款逻辑）；若要独立 iOS 设置需后台加 `ios_download_url` 配置项（暂未做）
- 落地页下载链接来源：`routes/web.php` 里直接 `view('theme::VP92.landing', [...admin_setting(...)])` 注入，与 Dashboard 的 API 方式不同但数据源相同

---

## 🔧 本次会话技术教训（新窗口必读，避免重复踩坑）

1. **SSH 到 HK 服务器很不稳定**：频繁 `Connection reset by peer` / `timed out`。对策：短命令快进快出；长操作拆步；失败先 `until ssh ... echo ok; do sleep 15; done` 后台等恢复再重试；scp 和 ssh 分开跑
2. **容器内是 BusyBox sh**：grep 无 `--include`；引号多层嵌套极易翻车（试过 heredoc 生成 Python 再生成 PHP，反斜杠转义炸了）。**复杂文件修改一律本地 Write 工具写好 → scp 上传 → php -l 语法检查 → docker cp 进容器**，这是唯一可靠路径
3. **Sanctum 认证验证 API 的方法**：user 表的 `token` 字段≠sanctum 凭证（Bearer 它会 403）。正确姿势：tinker 里 `$user->createToken('debug-probe',['*'],now()->addHour())` 取 plainTextToken 第2段做 `Authorization: Bearer xxx`，测完立刻 `PersonalAccessToken::where('name','debug-probe')->delete()`
4. **tinker --execute 引号转义地狱**：一律写成 PHP 文件 → scp → docker cp → `php artisan tinker /tmp/xxx.php`（已有约定，再次验证）
5. **查设置值**：`docker exec xboard php artisan tinker /tmp/check-app-settings.php`（脚本在 `E:\jiedian\check-app-settings.php`，遍历 admin_setting 打印）
6. **route:list 验证路由**：`docker exec xboard php artisan route:list 2>/dev/null | grep 关键词`

## 📋 Git Commits（本次会话，均已 push my master）
| Commit | 内容 |
|--------|------|
| `28e34f9` | feat: V1 user/app/getVersion 路由注册 - Dashboard应用下载卡片对接后台APP设置 |

（更早的 `4990acd`/`1c8b7ae`/`9a5edec`/`c62ee9f`/`9b7d42f` 见 v2 进度/memory）

## 📋 待处理事项汇总
1. **【进行中】版本更新通知群发邮件** — 见顶部任务A，从"问截止日期"继续
2. 群发后观察续费转化（v2_order 近几天新增订单）（遗留自 v2）

## 📋 新窗口唤醒提示词

```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。里面包含了当前状态、已完成的工作、待处理事项和精确的技术细节。按照文件顶部的"任务A：版本更新通知群发邮件"继续工作——从"问用户文案要不要写截止日期"这一步开始，然后呈现完整设计稿获批准后再实施（发送方案A已拍板：独立命令send:update-notice+模板updateNotice，收件人=非封禁非管理员全部用户）。严格遵守文件中"本次会话技术教训"一节的避坑指南。
```

## 📌 任务清单快照（brainstorming 流程进行到第4步）
- [x] 探索项目上下文（邮件基础设施）
- [x] 澄清需求（收件人范围、方案A已拍板）
- [x] 提出 2-3 个方案（用户选了方案A）
- [ ] 呈现完整设计稿并获批准 ← **下一步**
- [ ] 写设计文档 + 自审 + 用户审阅
- [ ] 转入实施计划（writing-plans）→ 实施部署 → dry-run → 测试邮件 → 全量群发
