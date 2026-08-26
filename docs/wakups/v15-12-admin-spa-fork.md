# v15.12 Admin SPA JS 注入 — 完成 ✅ + 遗留问题

> **接续**：v15.11 后端方案 B1 完成入库（commit cc580f2）+ v15.12 调研完成（commit 71d8ab6）
> **本会话状态**：v15.12 已完成上线入库（commit 73cac55 + 9cddc05 已 push my master）

## ✅ 已完成

**commit 73cac55 (feat admin v15.12 JS 注入浮动面板 + getUserInfoById 5 字段):**
- admin.blade.php +74 行: fetch 拦截 + Radix DOM 观察 + 浮动面板渲染
- UserController::getUserInfoById 补齐 5 字段 (register_geo/last_login_geo/last_login_client/devices/subscription_logs)
- **关键发现**: 原 getUserInfoById 是独立方法, 没调 transformUserData, 导致 5 字段全空

**commit 9cddc05 (面板内嵌 modal + IP 转字符串 + 去重字段):**
- panel 从 `position:fixed` 浮动改为 append 到 Radix modal DOM 内 (用户要求)
- 新增 last_login_ip_str/register_ip_str 行 (JS 端 long2ip fallback)
- 移除重复字段 (邮箱 modal 已有), 只保留 modal 没有的: IP/客户端/注册地/最后登录/设备/订阅记录

## 📦 部署位置

- **容器路径**: `/www/resources/views/admin.blade.php`
- **容器路径**: `/www/app/Http/Controllers/V2/Admin/UserController.php`
- **本地**: `E:\jiedian\xboard-repo\Xboard\Xboard-master\resources\views\admin.blade.php`
- **本地**: `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Http\Controllers\V2\Admin\UserController.php`

## ⚠️ 已知遗留（标记到 v15.13）

### 1. GeoIP city/province 空
- **症状**: 注册地/最后登录地 字段都是 `IPv6 暂不支持`
- **根因 (双重)**:
  - (a) ip2region 类名错: `\App\Utils\Ip2Region::instance()` 不存在
    - 实际是全局类 `Ip2Region` (无 namespace)
    - 文件路径: `/www/vendor/zoujingli/ip2region/Ip2Region.php`
    - 调用方式: `new Ip2Region()` (不是 `::instance()`)
    - `memorySearch` 返回 array 不是 string (GeoIpService 当前 `explode('|', $region)` 错了)
  - (b) Caddy + Cloudflare IPv6 透传, register_ip 是 IPv4 long 但实际是 IPv6 出口映射的 IPv4
- **修复方案**: 改 GeoIpService.php:
  - `\zoujingli\Ip2Region::instance()` → `\Ip2Region` (或 `new Ip2Region()`)
  - `$parts = explode('|', $region)` → 因为返回 array 应改为 `$region['region']` 然后 split
  - 或直接换外部 API (ip-api.com, ipinfo.io)

### 2. cedar SPA 整数 IP 渲染 (未修)
- **症状**: modal 里"最后登录IP"显示 `1879596323` 而不是 `112.8.93.35`
- **根因**: cedar SPA React 组件直接渲染 `last_login_ip` (long), 没用 `last_login_ip_str` (string)
- **临时方案**: JS 面板用 long2ip 转, 但 modal 原生显示仍是整数
- **修复方案**: 用户接受 (他说"用户详情页本身可以看到的就不用去改"), 暂不改

## 🎯 用户决策记录

- "本来用户详情页 就是读取, 又不需要写入什么东西" → C-0 JS 注入方案
- "下个新窗口直接开始不做小测试" → 不要小测试, 直接实施
- "后台应该又自带的啊" → 字段 IP 用 long2ip 转字符串, modal 整数不要改
- "用户详情页本身可以看到的就不用去改嘛" → 面板只显示 modal 没有的字段, 不重复

## 📂 关键代码位置

| 路径 | 用途 |
|------|------|
| `Xboard\Xboard-master\resources\views\admin.blade.php` | JS 注入 (内嵌 panel, append 到 Radix modal) |
| `Xboard\Xboard-master\app\Http\Controllers\V2\Admin\UserController.php` | getUserInfoById + transformUserData 5 字段 |
| `app\Services\GeoIpService.php` | GeoIP 查询 (待修类名) |

## 🔧 备用 / 后续方案

- **v15.13**: 修复 GeoIpService 类名 (5 行 PHP 改动)
- **v15.14**: 替换为外部 GeoIP API (国内推荐 ip-api.com, 海外 ipinfo.io)
- **v15.15**: cedar SPA fork 用户详情组件显示 5 字段 (已否决, 闭源 bundle)

## 🚨 关联记忆

- [[v15-11-admin-geoip-subscription-log]] — v15.11 后端
- [[feedback-rigor-no-fluff]] — 验证先于完成
- [[tool-selection-rules]] — 读本地文件用 Read
- [[xboard-theme-system]] — theme:: 命名空间
