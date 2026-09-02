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

### ⚠️ `/www/public/theme/VP92/` 容器外 vs 容器内不同物理路径（致命避坑！）

Docker overlayfs 行为：**容器内** `/www/public/theme/VP92/` 是 image layer + 容器可写 layer（**web 服务从这里读**）；**host** 上同名路径是 image 内置只读 inode（**Octane 看不到这里**）。

| 视角 | 路径 | 性质 |
|------|------|------|
| 容器内 | `/www/public/theme/VP92/assets/` | web 服务实际读取层 |
| host | `/www/public/theme/VP92/assets/` | image 内置只读 inode |

**正确验证部署**：
```bash
# ✅ 推荐：真实 HTTP 请求（最权威）
curl -sI https://vp92vip.com/theme/VP92/assets/index-CWK9ecsc.js  # 200 OK = 成功
curl -sI https://vp92vip.com/theme/VP92/assets/index-Dux9ywbC.js  # 404 = 旧 hash 已清

# ✅ 推荐：容器内 ls
docker exec xboard sh -c 'ls -la /www/public/theme/VP92/assets/'  # 看到新 hash = 成功

# ❌ 错误：host ls（会误导，看到的是 image 内置旧 inode）
ls /www/public/theme/VP92/assets/  # 仍显示旧 hash, 不要被误导
```

**历史教训**：v22/v23 memory 写「`md5 服务器 = md5 本地` 验证部署成功」是错的，那个 md5 是 host 视角，跟 web 服务实际读的不一致。v24 已发现并明确化。

### 前端部署流程（VP92 Vue SPA）
1. **本地构建**: `cd E:\jiedian\vp92-frontend && npx vite build`
2. **打包 assets**: `tar czf vp92-assets.tar.gz -C dist/assets .`
3. **上传到服务器**: `scp vp92-assets.tar.gz root@154.3.38.212:/tmp/`
4. **解压到 public/assets**: `docker cp /tmp/vp92-assets.tar.gz xboard:/tmp/ && docker exec xboard sh -c 'cd /www/public/theme/VP92/assets && rm -rf * && tar xzf /tmp/vp92-assets.tar.gz'`
5. **更新 blade hash（关键！）**: 改 `/www/theme/VP92/dashboard.blade.php` 中的 JS/CSS hash
6. **同步 public 副本**: `cp /www/theme/VP92/dashboard.blade.php /www/public/theme/VP92/dashboard.blade.php`
7. **清缓存 + reload**: `php artisan view:clear && php artisan cache:clear && php artisan octane:reload`

### vp92-frontend 边界（关键！）

vp92-frontend 是 jiedian monorepo 子目录，**仅 tracked 16 个文件**（src/views/auth/{Register,Login,Forgot}Password.vue + theme/dashboard.blade.php + 必要 entry 等）。**`src/locales/*` 4 文件不入库**（zh-CN.json / en-US.json / zh-TW.json / index.ts）。

**决策（v25 边界确认）**：
- ✅ **维持不入库**：locale 改动频繁（每次 i18n 改动 50KB+ diff 噪音），不入库保持 git history 干净
- ✅ v24 已验证：3 auth pages i18n 化 + zh-TW 新增 599 行全不入库，部署 + e2e 9/9 全部正常
- ✅ 跨会话靠 session memory 沉淀（`vp92-v24-auth-i18n-full.md` 记录了所有 locale 增量）

**灾难恢复**（locale 文件丢失时）：
1. `git log --all --diff-filter=D --name-only | grep locales` — 但因不入库, 默认查不到
2. **真实恢复路径**：从 session memory (`~/.claude/projects/E--jiedian/memory/`) 找 `vp92-v24-auth-i18n-full.md` 重建
3. 或手动从 vue-vendor chunk (`vue-vendor-CohP-jTm.js`) 反编译 t() key（不推荐，容易漏）

**改动 locales 的标准流程**：
1. 修改 + vite build 本地验证 OK
2. 写 session memory（必须！否则下次会话不知道改了啥）
3. **不要** `git add src/locales/`，下次会话维持 untracked
4. 极端情况（团队接入或 monorepo 拆分 vp92-frontend 独立仓库）才考虑入库

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

### ⚠️ 修改后端代码前必做：先拉服务器最新版（防旧代码覆盖！）
本地副本可能落后于线上（历史上发生过：AiController 孤儿文件、ServerService 多版本混乱）。**改任何后端 PHP 文件之前，必须先确认本地版本 = 服务器版本**：

```bash
# 1. 取服务器当前版本到本地临时文件
ssh root@154.3.38.212 "docker exec xboard cat /www/app/xxx.php" > /tmp/server_latest.php

# 2. md5 对比本地文件，判断是否一致
md5sum 本地文件.php /tmp/server_latest.php
```

- **一致** → 直接在本地改，然后走正常部署流程
- **不一致** → 以服务器版为基准合并修改，再上传覆盖；绝不拿本地旧版直接覆盖
- 改完部署成功后**立即 git commit 入库**（未入库的文件对下个会话来说等于不存在）

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
**Bot 由 supervisord 管理（telegram-ai / admin-ai / kawu-ai），优先用 `docker exec xboard supervisorctl restart telegram-ai` 重启。**

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
