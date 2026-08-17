# VP92极速加速器 — 项目说明

## 唤醒：新窗口启动时先读取最新进度
- `docs/session-progress-latest.md` — 当前最新进度（**必读**，自包含，无外部依赖）
- `deployment-guide.md` — 部署指南
- `project-paths.md` — 项目目录结构

### 新窗口唤醒词（直接复制粘贴）
```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。里面包含了当前状态、已完成的工作、待处理事项和精确的技术细节。按照文件中的"唤醒提示词"部分开始工作。
```

## 服务器
- **HK 服务器**: `root@154.3.38.212` (DMIT), Docker 容器 `xboard`
- **域名**: vp92vip.com（用户面板/落地页），vp92.app（订阅入口），vp92vip.cc.cd（APK/Edgetunnel）
- **SSH**: `ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212`

## Xboard 主题系统（重要！）
`ThemeService` 注册 `theme::` 视图命名空间（`app/Services/ThemeService.php`）：
```
theme::  →  /www/theme/              (系统主题，View::addNamespace)
theme::  →  /www/storage/theme/      (用户主题，优先级更高)
```
**`view('theme::VP92.dashboard')` 渲染的是 `/www/theme/VP92/dashboard.blade.php`，不是 `resources/views/` 或 `public/` 下的！**

| 容器内路径 | 是否被 view() 渲染 | 用途 |
|-----------|-------------------|------|
| `/www/theme/VP92/` | ✅ **真正被渲染的** | 系统主题源文件 |
| `/www/storage/theme/VP92/` | ✅ 优先级更高 | 用户上传主题 |
| `/www/public/theme/VP92/` | ❌ 仅静态资源服务 | assets JS/CSS 文件 |
| `/www/resources/views/theme/VP92/` | ❌ 不参与 | 不要改这里 |

### 前端部署流程（VP92 Vue SPA）
1. **本地构建**: `cd E:\jiedian\vp92-frontend && npx vite build`
2. **打包 assets**: `tar czf vp92-assets.tar.gz -C dist/assets .`
3. **上传到服务器**: `scp vp92-assets.tar.gz root@154.3.38.212:/tmp/`
4. **解压到 public/assets**: `docker cp /tmp/vp92-assets.tar.gz xboard:/tmp/ && docker exec xboard sh -c 'cd /www/public/theme/VP92/assets && rm -rf * && tar xzf /tmp/vp92-assets.tar.gz'`
5. **更新 blade hash（关键！）**: 改 `/www/theme/VP92/dashboard.blade.php` 中的 JS/CSS hash
6. **同步 public 副本**: `cp /www/theme/VP92/dashboard.blade.php /www/public/theme/VP92/dashboard.blade.php`
7. **清缓存 + reload**: `php artisan view:clear && php artisan cache:clear && php artisan octane:reload`

## 关键目录

| 路径 | 用途 |
|------|------|
| `E:\jiedian\xboard-repo\` | 主工作仓库（所有 Xboard 自定义代码，**TelegramAdminAiPoll.php 在这里**） |
| `E:\jiedian\vp92-frontend\` | VP92 Vue3 前端（Dashboard SPA，vite build） |
| `E:\jiedian\edgetunnel-extracted\` | Edgetunnel Worker 代码、补丁、部署脚本 |
| `E:\jiedian\xboard-theme-lighttrack\` | LightTrack Vue3 自定义前端主题 |
| `E:\jiedian\xboard-deploy\` | 部署调试脚本和参考副本 |
| `E:\jiedian\docs\` | 设计文档和规格说明 |

## 自定义模块 (XboardFerrari 命名空间)
位于 `app/XboardFerrari/` 和 `app/Services/Cloudflare/`:
1. **礼品卡系统** — GiftCardController, GiftCardService, 3 张表
2. **CF Worker 自动部署** — CfDeployService (7 步部署流程)
3. **Edgetunnel 订阅管理** — EdgetunnelService, DNS 自动管理, Hook 注入订阅
4. **Cloudflare 服务层** — CloudflareApiClient, CloudflareDnsService, CloudflareKvService

## 部署工作流

### 后端 PHP 代码部署
```bash
# 方法 1: Python 编辑 + scp pipe (推荐)
python -c "
content = open('local_file.php').read()
# 修改 content
import subprocess; subprocess.run(['scp','-','root@154.3.38.212:/www/app/...'], input=content.encode())
"

# 方法 2: deploy.bat (预配置文件列表)
deploy.bat

# 部署后必须
ssh root@154.3.38.212 "docker exec xboard php artisan octane:reload"
```

### Admin AI Bot 部署（TelegramAdminAiPoll.php）
**⚠️ 必须用本地格式化文件** `E:\jiedian\xboard-repo\app\Console\Commands\TelegramAdminAiPoll.php`
```bash
scp -i C:/Users/Administrator/.ssh/id_ed25519 \
  E:/jiedian/xboard-repo/app/Console/Commands/TelegramAdminAiPoll.php \
  root@154.3.38.212:/tmp/TelegramAdminAiPoll_local.php
ssh root@154.3.38.212 \
  "docker cp /tmp/TelegramAdminAiPoll_local.php xboard:/www/app/Console/Commands/TelegramAdminAiPoll.php && \
   docker exec xboard sh -c 'kill \$(pgrep -f telegram:admin-ai-poll) 2>/dev/null; sleep 1; \
   cd /www && nohup php artisan telegram:admin-ai-poll > /tmp/admin-ai-poll.log 2>&1 &'"
```
**不要用 Python 字符串替换修改压缩文件！直接 scp 本地文件。**
**Bot 不走 Octane，是独立 artisan 长驻进程，必须 kill+重启。**

### Edgetunnel Worker 部署
```bash
cd E:\jiedian\edgetunnel-extracted
npx wrangler deploy --config wrangler.jsonc
```

### 代码备份（每次会话结束前必做！）
确认代码修改在服务器上正常运行后，**必须执行 git commit + push**：
```bash
cd E:\jiedian\xboard-repo
git add -A
git commit -m "feat/fix: <简述本次修改>"
git push my master
```
- remote `my` = `github.com/kangtaze/xboard-ze.git`（你的 fork）
- **不要** push 到 `origin`（上游 Xboard）
- 推送前检查 `git config --global http.proxy`，如果有 `127.0.1` 就先 `--unset`

## 数据库
- SQLite: `/www/.docker/.data/database.sqlite`
- 表前缀: `v2_`
- **SQLite 不支持 `JSON_EXTRACT`** — 必须 PHP 端 foreach+json_decode 过滤

## 当前配置

| VIP | group_id | DNS Zone | CF Worker | CfWorker ID |
|-----|----------|----------|-----------|-------------|
| VIP1 | 2 | vp92.cc.cd (id=33) | Cloudflare Worker01 | 33 |
| VIP2 | 3 | vp92vip2.cc.cd (id=4) | Cloudflare Worker02 | 4 |
| VIP3 | 4 | vp92vip3.cc.cd (id=25) | Cloudflare Worker03 | 25 |
| VIP4 | 5 | — | 未配置 | — |

## 定时任务

| 任务 | 间隔 | 说明 |
|------|------|------|
| `sync:users-to-kv` | 每 30 分钟 | 活跃用户 UUID 同步到 KV（必须保留） |
| ~~`sync:edgetunnel-nodes`~~ | ~~每 30 分钟~~ | **已关闭**，改为手动在 Edgetunnel 节点管理点击同步 |
| KV-Sync-v5 (Windows) | 每 5 分钟 | 3 个 CF 账号 KV 用户同步（本地 Task Scheduler） |

## 网络环境（重要！）
- **路由器透明代理**，没有本地代理软件（Clash/V2Ray 等）
- `git push` 前必须检查 `git config --global http.proxy`，如果设了 `127.0.1` 就先 `--unset`

## 编码约定
- PHP 代码遵循 Laravel 12 标准，命名空间 `App\XboardFerrari`
- Worker 使用 JavaScript (ES2022+), Cloudflare Workers 运行时
- 部署到服务器时避免使用 `sed`（会破坏反斜杠），用 Python 编辑
- `EdgetunnelServiceProvider` 必须在 `OctaneServiceProvider` 之后注册
- Hooks 必须在 `WorkerStarting` 事件中注册
