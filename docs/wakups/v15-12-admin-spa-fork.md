# v15.12 — Fork Admin SPA 调研完成（2026-08-27）

> **接续**：v15.11 方案 B1 后端完成入库（commit cc580f2）
> **本会话**：调研 cedar admin SPA 来源/路由/fork 可行性
> **状态**：调研完成 ✅ / 关键发现：cedar 不公开 admin SPA 源码，fork 方案需要重选

## ⚡ 一句话状态

```
v15.11 ✅ 后端方案 B1 入库: commit cc580f2 (8 文件 +547 行)
v15.12 ✅ 本会话调研完成: 5 字段 API 真实存在, 路由映射清晰, 但 fork 不可行
⛔ fork 阻塞: cedar 只有 dist 仓库无源码
🎯 新方向: 三选项重新评估 (见 §fork 方案重选)
```

## 🔍 关键发现（10 项）

### 1. admin SPA bundle 真实位置

| 路径 | 用途 | 类型 |
|------|------|------|
| `/www/public/assets/admin/` | 物理路径（git submodule） | ✅ cedar 编译产物 |
| `/www/public/2d532679/` | **不存在**（虚构路径） | ❌ 不要找 |
| `/www/resources/views/admin.blade.php` | Laravel Blade 入口模板 | ✅ 渲染 SPA HTML |

### 2. 路由完整数据流

```
用户访问 /2d532679
  ↓
Caddy reverse_proxy → octane:7002
  ↓
Laravel routes/web.php:117
  Route::get('/{secure_path}', fn() => view('admin'))
  ↓
admin.blade.php 动态读 /www/public/assets/admin/manifest.json
  收集 entry/css/imports/scripts/locales
  ↓
输出 <script src="/assets/admin/assets/index-XXX.js">
  ↓
浏览器 GET /assets/admin/assets/index-XXX.js
  ↓
Caddy → octane 静态文件 → /www/public/assets/admin/assets/

admin API:
  /2d532679/edgetunnel → Caddy @admin rewrite → /api/v2/edgetunnel → AdminRoute
  /2d532679/user/fetch → Caddy 不匹配 → octane → admin.user.fetch route
```

### 3. SECURE_PATH 双向冗余（修改要重启容器）

| 层 | 来源 | 何时生效 |
|----|------|---------|
| **应用层** | `v2_settings.secure_path` (DB) | 即时（下次请求） |
| **Caddy 层** | `docker run -e SECURE_PATH=2d532679` | 容器重启 |
| **默认值** | `hash('crc32b', config('app.key'))` | 安装时生成 |

**踩坑**：改 admin 后台路径只改 DB 不重启容器 → Caddy 还路由旧路径 → 旧路径 200 但实际 API 在新路径 404

### 4. Caddy 实际配置（98 行 supervisord.conf）

```ini
[program:caddy]
command=caddy run --config /etc/caddy/Caddyfile --adapter caddyfile
# 没有 environment= 段，SECURE_PATH 来自 docker run -e
```

```caddy
@admin path /{$SECURE_PATH}/edgetunnel /{$SECURE_PATH}/cf-worker
rewrite @admin /api/v2{path}
reverse_proxy 127.0.0.1:{$OCTANE_INTERNAL_PORT:7002}
```

**注意**：`@admin` 只 rewrite 2 个特定路径（edgetunnel + cf-worker），其他 admin API 走 octane 全路由

### 5. bridge.blade.php 是另一个东西

`/www/resources/views/admin/bridge.blade.php`（v15.6 的"token bridge"）：
- 不是 admin SPA 入口
- 是 **用户面板跳转桥**（SPA token bridge）
- 从 localStorage 拿 XBOARD_ACCESS_TOKEN → 拼 _t query → 跳到目标页

### 6. admin API 5 字段 100% 真实（commit diff 验证）

`Xboard/Xboard-master/app/Http/Controllers/V2/Admin/UserController.php` cc580f2 改动：
- `last_login_ip_str` / `register_ip_str` (long2ip)
- `register_geo` / `last_login_geo` (country/province/city/isp)
- `last_login_client` (type/version/os, UA 解析)
- `devices` (最多 10 个 Sanctum tokens)
- `subscription_logs` (最多 10 次拉订阅记录)

### 7. cedar 上游 admin SPA 仓库真相

**致命发现**：cedar 不公开 admin SPA 源码！

```
cedar2025 (9 个仓库):
  ✅ Xboard             (PHP, 主面板)
  ⛔ xboard-admin-dist   (JS, 只有 dist 编译产物: assets/locales/index.html/manifest.json)
  ✅ xboard-user         (JS, 用户前端源码, ⭐39)
  ...
  ❌ 没有 xboard-admin (源码仓库不存在)
```

仓库结构证实：
- `https://github.com/cedar2025/xboard-admin-dist`
- 只有 4 项：assets/, locales/, index.html, manifest.json
- 无 src/, 无 README, 40 commits
- 17 forks

### 8. cedar admin SPA 技术栈（从 index.html + manifest.json 推断）

- **构建工具**：Vite（manifest.json 是 Vite 格式）
- **UI 框架**：React + TypeScript（monaco-editor + shadcn）
- **样式**：Tailwind + shadcn UI（Radix）
- **i18n**：en-US, ru-RU, zh-CN（3 个 locale 文件）
- **编辑器**：Monaco Editor（codicon 字体）
- **版本**：20260807-60ba571（Aug 7 2026）

### 9. 本地仓库 submodule 状态异常

```
容器:  /www/public/assets/admin/.git (gitlink 指向 ../../../.git/modules/...)
       但本地仓库没初始化 submodule！
       
本地 xboard-repo:
  .git/modules/public/assets/admin/   → 不存在
  Xboard/Xboard-master/.gitmodules   → 存在（cedar 上游定义）
  
结果: 服务器有 admin SPA bundle (Aug 7 编译), 本地仓库无
```

**部署影响**：升级 cedar 上游 → submodule 重 clone → 本地仓库不会自动同步

### 10. v15.11 已知遗留问题（确认）

- **GeoIP 字段空**：Caddy + Cloudflare 把 IPv6 edge IP 透传 → ip2region.xdb 只 IPv4 → country/province/city/isp 全空
- **修复方向**：配 Laravel TrustProxies 解析 `X-Forwarded-For`，但 Caddyfile 已设 `trusted_proxies static 0.0.0.0/0 ::/0`，问题在 ip2region.xdb 本身
- **app 前端未动**：cedar 上游 SPA bundle，决策 B：自己 fork

## 🎯 v15.12 fork 方案重选（基于新发现）

### ❌ 原方案：fork cedar admin SPA 源码 — **不可行**
- 阻塞：cedar 无公开 admin SPA 源码仓库
- xboard-admin-dist 只有 dist 产物，无法 fork 改源码

### 三选项重新评估

| 方案 | 工作量 | 风险 | 体验 | 推荐度 |
|------|--------|------|------|--------|
| **A. 反编译 xboard-admin-dist** 反编译 Vite 产物恢复 React 源码 | 5-7 天 | 高（编译产物可能丢失原信息） | 全 SPA 体验 | ⭐⭐ |
| **B. 自己从零重写 admin 用户详情组件** | 3-5 天 | 中（需维护独立代码） | 全 SPA 体验 | ⭐⭐⭐ |
| **C. 不 fork SPA，用 Blade 新增用户详情页** `/2d532679/user-detail/{id}?token=XXX` | 1-2 天 | 低（独立 Blade 不依赖 SPA） | 离开 SPA | ⭐⭐ |

### 推荐：方案 B（自己重写 React 用户详情组件）

**思路**：
1. 用 Vite 单独构建一个新 `vp92-admin-user-detail` 包
2. 在 `admin.blade.php` 里检测当前 SPA 版本，如果是我们的硬编码重定向到新组件 URL
3. 或者更激进：**整个替换 admin SPA**，用 vite 重新构建 React + shadcn UI（前端整体重写）
4. 或者最务实：只覆盖 `/2d532679/user/:id` 这一个路由，**patch cedar SPA 源码**（但要源码...）

### 推荐：方案 C（最务实！）

**思路**：
1. 在 `/www/resources/views/admin/` 加 `user-detail.blade.php`
2. 在 `routes/web.php` 加：`Route::get('/{secure_path}/user-detail/{id}', ...)`
3. Blade 页面用 Vue3 / Alpine.js / 直接 fetch admin API 渲染 5 字段
4. 在 admin SPA bundle 里（如果有源码）patch 一个跳转，但 cedar 没源码...
5. **更简单**：在 admin SPA 顶部加个 banner，提示"用户详情页已迁移到 /2d532679/user-detail/:id"
6. 真正最简单：在 admin.blade.php 头部加一段 JS 拦截 `user/fetch`/`user/getUserInfoById` 调用，自动跳转

## 📋 v15.12 待决策（按优先级）

1. **P0**：用户决策 fork 方案（A/B/C/其他）
2. **P1**：如选 B 或 C，明确：维护成本 vs 体验 vs 工作量
3. **P2**：同时解决 v15.11 GeoIP 遗留（IPv4 xdb 限制）

## 📂 关键文件位置速查

| 路径 | 用途 |
|------|------|
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Http\Controllers\V2\Admin\UserController.php` | admin transformUserData 补字段 (cc580f2) |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Http\Routes\V2\AdminRoute.php` | admin API 路由 (prefix=secure_path) |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\routes\web.php:117` | /{secure_path} → view('admin') |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\resources\views\admin.blade.php` | 动态读 manifest.json 渲染 SPA |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\resources\views\admin\bridge.blade.php` | 用户面板 SPA token bridge |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\.gitmodules` | cedar 上游: public/assets/admin → cedar2025/xboard-admin-dist |
| `/www/.docker/.data/database.sqlite` | SQLite (v2_settings 表 secure_path) |
| `/www/.docker/supervisor/supervisord.conf` | supervisord 98 行 (caddy 程序段无 environment) |
| `/etc/caddy/Caddyfile` | Caddyfile (active, @admin 路由) |
| `https://github.com/cedar2025/xboard-admin-dist` | ❌ cedar admin SPA **只有 dist，无源码** |

## 🚨 v15.12 关联记忆

- [[v15-11-admin-geoip-subscription-log]] — v15.11 完整进度
- [[feedback-rigor-no-fluff]] — 验证先于完成（本次调研体现）
- [[tool-selection-rules]] — 读本地文件用 Read
- [[xboard-theme-system]] — theme:: 命名空间
- [[xboard-caddyfile-config-location]] — active 是 /etc/caddy/Caddyfile