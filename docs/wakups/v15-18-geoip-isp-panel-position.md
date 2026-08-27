# v15.18 — GeoIP 4 段解析修正 + Panel 定位 ✅ 完成入库

> **接续**: v15.17 panel 定位错 (modal 末尾) + 缺 ISP
> **本次完成**: GeoIpService off-by-one + admin.blade.php panel 精准定位到「订阅信息」卡片下方
> **入库**: commit 0b2bd7b push my master `a4edfab..0b2bd7b`

## ✅ 已完成

**commit 0b2bd7b (fix admin GeoIP + panel 定位):**

### 1. GeoIpService.php off-by-one 修复 (12 行改动)
- **根因**: ip2region 实际返回 **5 段** `国家|0|省|市|ISP`, `parts[1]` 是区域标记位(总为 0)
- **旧错**: 读 parts[0..3] → country=中国, province=0(空), city=省, isp=市
- **新正**: 读 parts[0,2,3,4] → country/province/city/isp 全部正确

**实测结果** (服务器 tinker):

| IP | country | province | city | isp |
|----|---------|----------|------|-----|
| 112.8.93.35 (用户 #329) | 中国 | 山东省 | 菏泽市 | **移动** |
| 1.81.201.28 (用户 #256) | 中国 | 陕西省 | 汉中市 | **电信** |
| 114.114.114.114 (中国 DNS) | 中国 | 江苏省 | 南京市 | (空) |
| 8.8.8.8 (Google DNS) | 美国 | | | Level3 |

### 2. admin.blade.php Panel 定位 (67 行改动)

**问题**: cedar admin SPA kebab 菜单只有「编辑」无「详情」, 但用户详情实际通过**点击 email 列**打开 (不是 kebab)。

**Modal 结构发现**:
- 7 卡片都在 `<div class="space-y-4">` 包装内
- 单卡片类: `div.rounded-lg.border.p-4`
- 旧 selector `querySelectorAll('div')` 误匹配 space-y-4 包装 → panel 落 modal 末尾

**新 finder (v15.16)**:
```js
const subInfoCard = Array.from(sheet.querySelectorAll('div.rounded-lg.border.p-4')).find(d => {
  const t = (d.textContent || '').trim();
  return t.startsWith('订阅信息');
});
```

**新 selector (v15.15)**: `div[role="dialog"][data-state="open"]` (覆盖 Sheet+Modal 通用)

**新 MutationObserver (v15.16)**: 监听 modal 子节点, 等 subInfoCard 出现后再插入 panel

## 🧪 浏览器实测 (用户 #329)

**panelAfterSubCard: true** ✓ (panel 在订阅信息卡片正下方)

```
📍 VP92 增强信息 (v15.16) #329
最后登录IP: 112.8.93.35
注册IP: 112.8.93.35
客户端: vp92-android 1.2.6 / Android
注册地: 中国 / 山东省 / 菏泽市 / 移动
最后登录: 中国 / 山东省 / 菏泽市 / 移动
📱 设备 (1)
📡 订阅记录 (1)
```

## 📂 关键代码位置

| 路径 | 用途 |
|------|------|
| `Xboard/Xboard-master/app/Services/GeoIpService.php` | 5 段解析 (改 1 行: parts[1]→parts[2], 加 parts[4]) |
| `Xboard/Xboard-master/resources/views/admin.blade.php` | panel 渲染 + 定位逻辑 |
| `/www/vendor/zoujingli/ip2region/Ip2Region.php` | 全局类, 必须 `\Ip2Region` |
| `/www/vendor/zoujingli/ip2region/ip2region.xdb` | 11 MB 数据 |

## 🎯 用户决策记录

- 用户 2026-08-27 要求 panel 放在「订阅详情卡片」(实际即订阅信息卡片)下面
- 用户要求显示 ISP (中国 / 山东省 / 市区 / 电信 移动 联通)
- 关键发现: 点击 user **email 列** (不是 kebab) 打开用户详情 modal

## 🚨 已知遗留 (后续 v15.19+)

- GeoIP IPv6 仍不支持 (`return $empty` 立即返回) — last_login_ip 为 IPv6 时显示「IPv6 暂不支持」
- Caddy+CF IPv6 透传问题: register_ip 是 IPv4 long 但实际是 IPv6 出口映射, 需 x-forwarded-for 解析
- 海外 ISP 名称不准确 (8.8.8.8 返回「Level3」, 应显示 Google 或 Comcast 等)
- admin 前端未动 (cedar 上游) — 仍是 JS 注入方案

## 🔧 备用 / 后续方案 (v15.19+)

- v15.19: 海外 IP 改用 ip-api.com / ipinfo.io 增强精度 (中国 IP 仍用 ip2region 全内存)
- v15.20: cedar SPA fork 用户详情组件显示 5 字段 (已否决, 闭源 bundle)
- IPv6 支持: 启用 ip2region 的 IPv6 xdb (15 MB 额外数据文件)

## 🚨 关联记忆

- [[v15-12-admin-spa-fork]] — 上游: v15.12 admin SPA JS 注入
- [[v15-13-geoip-fix]] — 上游: v15.13 类名/命名空间/memorySearch 修复
- [[v15-11-admin-geoip-subscription-log]] — 上游: v15.11 后端 5 字段
- [[admin-spa-credentials]] — 测试账号 admin@demo.com
- [[feedback-rigor-no-fluff]] — 验证先于完成
- [[tool-selection-rules]] — 读本地文件用 Read
- [[xboard-theme-system]] — theme:: 命名空间