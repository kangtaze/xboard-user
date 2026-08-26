# v15.13 — GeoIP 类名/命名空间修复 ✅ 完成入库

> **接续**：v15.12 admin SPA JS 注入完成入库（commit 73cac55 + 9cddc05）
> **本次完成**：GeoIpService 类名/命名空间/memorySearch 返回类型 三处错误修复（commit 709ab26）
> **入库**：push my master `9cddc05..709ab26`

## ✅ 已完成

**commit 709ab26 (fix GeoIp 类名/命名空间 + memorySearch 返回 array):**
- `App\Services\GeoIpService.php` 修改 5 行 (2 行代码 + 3 行注释):
  - 错误 1: `\zoujingli\Ip2Region::instance()` 不存在
    - 实际: 全局类 `Ip2Region` 在 `/www/vendor/zoujingli/ip2region/Ip2Region.php`
    - 无 `::instance()` 静态方法，只有 `__construct()`
  - 错误 2: 命名空间上下文
    - `GeoIpService` 在 `App\Services` 命名空间下，`new Ip2Region()` 被解析为 `App\Services\Ip2Region`
    - **必须用 `\Ip2Region` 前导反斜杠强制全局命名空间**（v15.12 修复第一版漏了）
  - 错误 3: `memorySearch()` 返回类型
    - 返回 array: `['city_id' => 0, 'region' => '中国|省|市|ISP']`，不是 string
    - 旧代码 `explode('|', $region)` 把 array 当 string 处理，得到空结果
    - 新代码 `explode('|', $region['region'] ?? '')` 正确取 'region' 字段

## 🐛 调试过程（v15.13 教训）

1. **第一次修复** (md5 `a43c8771...`): `new Ip2Region()` — tinker 单测空
2. **查 laravel.log** 发现新错误: `Class "App\Services\Ip2Region" not found`
   - **根因**：命名空间冲突，本类在 `App\Services` 下，PHP 自动补全
3. **第二次修复** (md5 `1bdbbcb0...`): `\Ip2Region` 前导反斜杠 — tinker 单测成功
4. **端到端验证**: admin 用户 #329 API 返回 register_geo + last_login_geo 真实数据

⚠️ **教训**：在命名空间文件中引用全局 PHP 类，**永远前导反斜杠**。v15.12 调研文档已知此类名错，但忽略了命名空间问题，是低级错误。

## 🧪 测试结果（v15.13 服务器实测）

| IP | country | province | city | isp |
|----|---------|----------|------|-----|
| 8.8.8.8 (Google DNS) | 美国 | | | |
| 1.1.1.1 (Cloudflare DNS) | 澳大利亚 | | | |
| 114.114.114.114 (中国 DNS) | 中国 | | 江苏省 | 南京市 |
| **112.8.93.35 (用户 #329)** | 中国 | | 山东省 | 菏泽市 |

admin SPA 用户详情面板 (v15.12 注入) 现在显示真实地理信息。

## 📂 关键代码位置

| 路径 | 用途 |
|------|------|
| `Xboard/Xboard-master/app/Services/GeoIpService.php` | GeoIP 查询服务（已修） |
| `/www/vendor/zoujingli/ip2region/Ip2Region.php` | 全局类，必须 `\Ip2Region` 引用 |
| `/www/vendor/zoujingli/ip2region/ip2region.xdb` | 11 MB 数据文件 |

## 🎯 用户决策记录

- 用户 2026-08-27 提供 admin SPA 测试账号：`admin@demo.com` / `0bb96173f6d925f3ee7e9e9c216cd6a1` (存入 memory `admin-spa-credentials.md`)
- 不再使用 kangtazezege@gmail.com 登录 SPA（那是邮件管理测试账号）

## 🚨 关联记忆

- [[v15-12-admin-spa-fork]] — 上游：v15.12 admin SPA JS 注入
- [[v15-11-admin-geoip-subscription-log]] — 上游：v15.11 后端 5 字段
- [[admin-spa-credentials]] — 测试账号 admin@demo.com
- [[feedback-rigor-no-fluff]] — 验证先于完成
- [[tool-selection-rules]] — 读本地文件用 Read
- [[xboard-theme-system]] — theme:: 命名空间

## 🔧 备用 / 后续方案（v15.14+）

- v15.14: 替换 ip2region 为外部 API（ip-api.com 国内 / ipinfo.io 海外）— 海外 IP 精度提升
- v15.15: cedar SPA fork 用户详情组件显示 5 字段（已否决，闭源 bundle）
- Caddy + CF IPv6 透传问题：register_ip 是 IPv4 long 但实际是 IPv6 出口映射，需 x-forwarded-for 解析