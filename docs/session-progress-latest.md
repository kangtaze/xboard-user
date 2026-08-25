# 🚨 v15.2 紧急唤醒快照 (2026-08-26)

> 用户紧急指令："上下文满了，新窗口要能马上接手项目进度，不能有脱节"
> **新窗口第一件事：复制粘贴底部"唤醒提示词"**

## 当前状态 (2026-08-26 凌晨)

### ✅ 已完成 + 部署 + 入库 (本会话完成)
| Task | Commit | 部署状态 |
|------|--------|----------|
| P0-C sitemap.xml 入库 | `b449ec7` | ✅ 已 push |
| P0-D 落地页 CORS/注册按钮修复 | `33a92fe` | ✅ 已部署 + push |
| P0-E Dashboard 加载旋转字体修复 | `33a92fe` | ✅ 已部署 + push |

### 🟡 进行中
- **P0-A**: PC/APK 扫码付款"已支付" BUG
  - **代码修复完成**：两端 `lib/xboard/api_service.dart` checkOrder() 已改为读 `res['data']` 整数（与 web v15.1 CheckoutDialog fix 对齐）
  - **修改文件**:
    - `E:\jiedian\PC-vp92\lib\xboard\api_service.dart` (line 660-680)
    - `E:\jiedian\vp92-flclash\lib\xboard/api_service.dart` (line 861-882)
  - **未部署**：需要 build + R2 上传 + version.json bump（v1.2.6 发布）
  - **预计耗时**: 30-60 分钟
  - **关键约束**:
    - PC-vp92 → Windows build → R2 (`vp92-releases/windows/`) → version-windows.json bump
    - vp92-flclash → Android build (split-per-abi) → R2 (`vp92-releases/apk/`) → version.json bump (+2000) → Play Store
    - 必须 `--remote` 给 wrangler r2 (否则上传到本地实例)
    - APK versionCode = base + 2000

### ⏸️ 待办
- **P0-B**: 教程图片恢复 (`/img/tutorial/`)
  - **17 张图全丢**：git 历史无、服务器无、R2 无、本地无
  - **用户说"以前给过你"** → 但本次会话搜索 E:/jiedian 全树找不到任何 `pc-*.jpg`/`mobile-*.jpg`
  - **可能位置**:
    - 用户 AppData\Local\Temp
    - 旧 vp92-deploy / vp92-reference 备份
    - 用户的下载文件夹 / 桌面
    - 某个旧的 R2 备份桶
  - **新窗口第一件事**：问用户"你之前给过我教程图，原始位置是？"
  - **替代方案**:
    - 用 v15.2 已部署的 PC/APK 截图重新生成
    - 临时用 placeholder 图替代
    - 教程页改纯文字+SVG图标，去掉图片依赖

## 🚨 服务器规则（v15.2 修订）

> **本地才是真权威！** 服务器可能被回滚，本地副本可能落后于线上
> 改任何后端 PHP 前**必须**：
> 1. `git log` 看最新本地 commit
> 2. `ssh root@154.3.38.212 "docker exec xboard cat /www/app/xxx.php" > /tmp/server_latest.php`
> 3. `md5sum` 对比，本地 != 服务器时**以服务器为基准合并修改**

## 关键文件路径速查

| 文件 | 路径 |
|------|------|
| 后端 PHP 源码 | `E:\jiedian\xboard-repo\` |
| 前端 VP92 主题 | `E:\jiedian\vp92-frontend\` |
| PC 客户端 | `E:\jiedian\PC-vp92\` |
| APK/移动客户端 | `E:\jiedian\vp92-flclash\` |
| Edgetunnel Worker 代码 | `E:\jiedian\edgetunnel-extracted\` ❌ **不要读** |
| 部署脚本 | `E:\jiedian\xboard-deploy\` |
| 设计文档 | `E:\jiedian\docs\` |

## 服务器速查
- SSH: `ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212`
- 容器: `xboard` (Laravel + Octane + Caddy)
- 路径: `/www/app/`, `/www/theme/VP92/`, `/www/public/theme/VP92/`, `/www/plugins-core/`
- Caddyfile: `/etc/caddy/Caddyfile` (不是 `/www/.docker/caddy/`)
- SQLite: `/www/.docker/.data/database.sqlite` (表前缀 `v2_`)

## 当前线上版本
- Web Dashboard: v15.2（commit 33a92fe deployed）
- PC/APK: v1.2.5 (即将升 v1.2.6)

## 关键 md5 / commit hash
- 当前 master HEAD: `33a92fe`
- v15.1 checkout fix: `fbc54ae`
- v15.2 sitemap: `b449ec7`
- v15.2 frontend fix: `33a92fe`

## 🚨 网络注意（刚刚遇到！）
- `git push my master` 不稳定，连续 3 次失败才通
- 失败模式: `Failed to connect to github.com port 443` / `schannel: server closed abruptly`
- **解决**: 等待 20-30 秒后重试
- SSH 也有同样问题: `Connection closed by 154.3.38.212 port 22` (刚才 scp dashboard.blade.php 时遇到过)
- `git config --global --unset http.proxy` 必须做（router 透明代理）

## 🟢 三大 Bot 进程
```
telegram-ai  PID 21162  ✅
telegram-adm PID 21166  ✅
kawu-ai      PID 21170  ✅
```
supervisord 主进程没 socket，supervisorctl 用不了，bot 必须 `setsid` 启动（之前 wakeup v15.2 已记录）

## 待办优先级（接手立即做）

1. **P0-A 构建部署** (1 小时内)：v1.2.6 发布
2. **P0-B 图片源**：问用户原始位置
3. **记忆库合并**：本文件替换旧的 `v15-2-emergency-wakeup.md`

---

## 📢 唤醒提示词（**新窗口直接复制粘贴**）

```
读取 E:\jiedian\docs\session-progress-latest.md，
这是 VP92 Xboard 项目最新进度（v15.2 凌晨紧急快照）。
按 "## 当前状态" 和 "## 待办优先级" 顺序接手。

完成的事：C/D/E 已 commit 33a92fe + b449ec7 入库+部署+push。
未完成：A 待构建部署（PC/APK v1.2.6），B 待用户提供素材。

所有 ssh 命令加 -i C:/Users/Administrator/.ssh/id_ed25519。
CF Worker 代码（E:\jiedian\edgetunnel-extracted）绝对不要读取。
完整流程链路: 用户 → Xboard(/s/{token}) → Ho→ EdgetunnelServiceProvider → DB 缓存 →注入订阅。
改任何后端 PHP 前先 git log + md5 对比本地（本地是真权威，服务器可能被回滚）。
git push my 前 unset proxy（github.com 直连 OK）并 sleep 20-30 防失败。

最关键第一步：问用户"教程图你之前给过我，原始位置是？"
（图片已查全树 E:/jiedian 无 pc-*.jpg / mobile-*.jpg）
```