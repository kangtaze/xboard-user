# VP92极速加速器 — 最新项目进度

> **最后更新**: 2026-08-17
> **状态**: ✅ 全部完成（免费体验24h + WhyChoose/FAQ视觉美化已部署）

---

## 唤醒提示词（直接复制粘贴到新窗口）

```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。里面包含了当前状态、已完成的工作、待处理事项和精确的技术细节。按照文件中的"唤醒提示词"部分开始工作。
```

---

## ✅ 已完成：落地页免费体验时间修复（2026-08-17）

**文件**：`E:\jiedian\vp92-frontend\theme\landing.blade.php`

所有"3 小时"免费试用已改为"24 小时"（共 16 处）：
- hero.trial_note（zh-CN/zh-TW/en）
- plans.subtext（zh-CN/zh-TW/en）
- why.trial_desc（zh-CN/zh-TW/en）
- faq.q5_a（zh-CN/zh-TW/en）
- faq.q6_a（zh-CN/zh-TW/en）
- $faqSchema JSON-LD 结构化数据（2处）

服务器确认 `try_out_hour = 24`。

---

## ✅ 已完成：Why Choose + FAQ 视觉美化（2026-08-17）

**文件**：`E:\jiedian\vp92-frontend\theme\landing.blade.php`

### Why Choose 板块改造：
- 渐变背景 + 装饰性光晕球（radial-gradient + blur(80px)）
- Stats 行：300+ CDN 节点 / 30+ 国家 / 99.9% 在线率
- 6 张 glass-morphism 卡片（`backdrop-filter: blur(12px)`），左侧品牌色边框装饰
- 渐变图标容器 + hover 放大动画

### FAQ 板块改造：
- 8 个编号 badge（01-08），展开时品牌色填充 + 深色文字
- 分类标签（关于/安全/客服/计费/使用），各有独立颜色
- glass-morphism 卡片样式
- toggleFaq() 动画：编号填充、边框发光、box-shadow 品牌光晕
- 已在线验证：展开/折叠交互正常 ✅

---

## ✅ 已完成：vp92vip.com 订阅隔离（工信部风险隔离）

### 目标
让 vp92vip.com（官方域名）不暴露任何订阅内容，避免被工信部检测封禁。

### 方案架构
```
用户订阅链接显示 vp92.app（CF Worker）
    → Worker 代理到 dns.vp92vip.cc.cd/s/{token}（后端通道域名）
        → CF 代理 → Nginx（Host=dns.vp92vip.cc.cd，不匹配vp92vip.com）
            → Octane → 返回订阅数据

同时：
    → 直接访问 vp92vip.com/s/{token} → Nginx 屏蔽 → 404 ✅
```

### 实现内容

**1. Nginx 配置**（HK 服务器 `/etc/nginx/conf.d/xboard.conf`）：
- vp92vip.com/s/ 屏蔽返回 404
- dns.vp92vip.cc.cd 在 server_name 中，正常代理到 Octane
- 本地副本：`E:\jiedian\xboard-deploy\xboard.conf`

**2. CfDeployService 修改**（`xboard-repo/app/Services/CfDeployService.php` 第 195 行）：
- 原：`admin_setting('app_url', 'https://ferrari.cc.cd')`（ferrari.cc.cd 已 NXDOMAIN）
- 新：`admin_setting('xboard_api_origin', 'https://dns.vp92vip.cc.cd')`

**3. CF Worker XBOARD_ORIGIN**：
- vp92.app Worker XBOARD_ORIGIN → `https://dns.vp92vip.cc.cd` ✅
- VIP2/VIP3 Worker 还未改（不影响，VIP 走 /sub KV 路径）

**4. dns.vp92vip.cc.cd 域名信息**：
- DNS A 记录 → 154.3.38.212，CF 代理（橙色云朵）
- Zone ID: `6c66411b5b17dc42a348e1ebcce8b75d`
- Account ID: `7e644a931e3a1273db139d1e944fef24`
- SSL: Active（通配符 *.vp92vip.cc.cd）

### 验证结果（全部通过）
| 测试 | 结果 |
|------|------|
| vp92vip.com/s/ | 404 ✅ |
| vp92vip.com/ | 200 ✅ |
| dns.vp92vip.cc.cd/ | 正常 |
| dns.vp92vip.cc.cd/s/ | 正常 |
| vp92.app/s/ | 正常 |
| VIP1 vp92.cc.cd/sub | 200 ✅ |
| VIP2 vp92vip2.cc.cd/sub | 200 ✅ |
| VIP3 vp92vip3.cc.cd/sub | 200 ✅ |

---

## ✅ 已完成：落地页 SEO 内容丰富（2026-08-17）

在 `landing.blade.php` 新增了两个 SEO 板块：
1. **"为什么选择 VP92"** — 4 个特性卡片（CF网络/30+节点/不限速/免费试用）
2. **"常见问题 FAQ"** — 8 个 Q&A 手风琴，含 JSON-LD FAQPage 结构化数据

已部署到服务器并在线生效，但视觉设计需要进一步美化（见任务2）。

---

## ✅ 已完成：Sitemap.xml SEO 修复（2026-08-17）

- sitemap.xml 返回 200，Content-Type: application/xml ✅
- XML 声明 `<?xml version="1.0" encoding="UTF-8"?>` 存在 ✅
- Nginx 添加 sitemap.xml + robots.txt 缓存控制（no-cache）✅
- lastmod 更新为 2026-08-17 ✅
- 本地副本：`E:\jiedian\xboard-repo\public\sitemap.xml`

---

## 待处理事项

### 1. 数据库自动备份（方案已设计，未实施）
- SQLite `/www/.docker/.data/database.sqlite` 在 Docker 容器内
- 方案：服务器 cron 每天凌晨备份，gzip 压缩，保留 7 天

### 2. VIP2/3 Worker XBOARD_ORIGIN 可选更新
- VIP2/3 Worker 的 XBOARD_ORIGIN 仍为 vp92vip.com
- 不影响功能（VIP 走 /sub KV 路径），但 /s/ 路径会 404

### 3. GitHub Actions Docker Build workflow 失败
- fork `kangtaze/xboard-ze` 是私有仓库，Docker build 时 git clone 没权限
- 建议禁用（对部署无用）

---

## ✅ VIP 状态（全部正常）

| VIP | CF Worker | 域名 | 订阅 ID | group_ids | DNS Worker ID | 状态 |
|-----|-----------|------|---------|-----------|--------------|------|
| VIP1 | Worker01 (id=33) | vp92.cc.cd | 4 | [2] | 33 | ✅ |
| VIP2 | Worker02 (id=34) | vp92vip2.cc.cd | 2 | [3] | 34 | ✅ |
| VIP3 | Worker03 (id=35) | vp92vip3.cc.cd | 5 | [4] | 35 | ✅ |

---

## 服务器和部署

- **HK 服务器**: `root@154.3.38.212` (DMIT), Docker 容器 `xboard`
- **Octane**: 端口 7001, Nginx 监听 80 代理到 localhost:7001
- **域名**: vp92vip.com（面板）, vp92.cc.cd / vp92vip2.cc.cd / vp92vip3.cc.cd（Worker 订阅）, dns.vp92vip.cc.cd（后端通道）
- **SQLite**: `/www/.docker/.data/database.sqlite`, 表前缀 `v2_`（**不支持 JSON_EXTRACT**）

### 落地页部署命令速查

```bash
# scp 本地 landing.blade.php 到服务器
scp -i C:/Users/Administrator/.ssh/id_ed25519 E:/jiedian/vp92-frontend/theme/landing.blade.php root@154.3.38.212:/tmp/landing.blade.php

# docker cp 到两个位置 + 清缓存
ssh root@154.3.38.212 "docker cp /tmp/landing.blade.php xboard:/www/theme/VP92/landing.blade.php && docker cp /tmp/landing.blade.php xboard:/www/public/theme/VP92/landing.blade.php && docker exec xboard sh -c 'php artisan view:clear && php artisan cache:clear && php artisan octane:reload'"
```

### 后端 PHP 代码部署
```bash
scp -i C:/Users/Administrator/.ssh/id_ed25519 <local.php> root@154.3.38.212:/tmp/
ssh root@154.3.38.212 "docker cp /tmp/<file> xboard:/www/app/... && docker exec xboard php artisan octane:reload"
```

### Admin AI Bot 部署（独立进程，不走 Octane）
```bash
scp -i C:/Users/Administrator/.ssh/id_ed25519 \
  E:/jiedian/xboard-repo/app/Console/Commands/TelegramAdminAiPoll.php \
  root@154.3.38.212:/tmp/TelegramAdminAiPoll_local.php
ssh root@154.3.38.212 \
  "docker cp /tmp/TelegramAdminAiPoll_local.php xboard:/www/app/Console/Commands/TelegramAdminAiPoll.php && \
   docker exec xboard sh -c 'kill \$(pgrep -f telegram:admin-ai-poll) 2>/dev/null; sleep 1; \
   cd /www && nohup php artisan telegram:admin-ai-poll > /tmp/admin-ai-poll.log 2>&1 &'"
```

### Nginx reload
```bash
scp -i C:/Users/Administrator/.ssh/id_ed25519 E:/jiedian\xboard-deploy\xboard.conf root@154.3.38.212:/tmp/
ssh root@154.3.38.212 "cp /tmp/xboard.conf /etc/nginx/conf.d/xboard.conf && nginx -t && nginx -s reload"
```

---

## CF 凭证速查

| 用途 | Zone | Zone ID | Account ID |
|------|------|---------|------------|
| VIP1 (vp92.cc.cd) | vp92.cc.cd | 33 | 7e644a93... |
| VIP2 (vp92vip2.cc.cd) | vp92vip2.cc.cd | 4 | d8fad819... |
| VIP3 (vp92vip3.cc.cd) | vp92vip3.cc.cd | 25 | 6902dc12... |
| 后端通道 (dns.vp92vip.cc.cd) | vp92.cc.cd | 6c66411b... | 7e644a93... |
| 官方面板 (vp92vip.com) | vp92vip.com | 8ea94456... | d405b699... |

---

## 关键技术信息

### Token 计算公式（所有 VIP 通用）
```
token = MD5(MD5(host + UUID).hex()[7:27])
```

### Laravel 验证规则陷阱
`|` 分隔是 **AND 逻辑**：`integer|string` = 要求同时是整数和字符串（错误）

### Worker 路由逻辑
- `/s/{token}` → 代理到 XBOARD_ORIGIN/s/{token}（需要后端）
- `/sub?token=xxx` → 本地从 KV 生成订阅（不需要后端）

### 已知问题
- jpuuc.cc.cd NXDOMAIN（VIP2 旧域名，不影响）
- database locked（SQLite 并发写入常见，非关键）
- GitHub Actions Docker Build 失败（私有仓库 clone 权限，无用 workflow）
