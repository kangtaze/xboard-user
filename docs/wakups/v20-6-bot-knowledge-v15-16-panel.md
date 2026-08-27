# v20.6 — Bot 知识库加 v15.16 增强信息面板 ✅ 完成入库

> **用户反馈**: 客服 Bot + 管理 Bot 不知道「📍 VP92 增强信息 (v15.16)」面板是什么
> **本次完成**: 3 个 Bot system prompt 注入 v15.16 知识 + 服务器部署 + E2E 验证
> **入库**: commit 10c0e7e push my master `16f7a24..10c0e7e`

## ✅ 已完成

**commit 10c0e7e (feat(bot): v20.6 — 3 个 Bot system prompt 加 v15.16 增强信息面板知识):**

### 1. 修改 3 个 Bot 文件（共 +59 行）

| 文件 | system prompt | v15.16 段位置 |
|------|---------------|---------------|
| `app/Services/AiChatService.php` | 客服 Bot user-facing（普通用户） | line 195 起（详尽版） |
| `app/Console/Commands/TelegramAiPoll.php` | 客服 Bot admin-via-customer（管理员用客服 Bot） | line 565 起（简洁版） |
| `app/Console/Commands/TelegramAdminAiPoll.php` | 管理 Bot staff + admin 双角色 | line 309（staff 段） + line 327（admin 段，详尽版） |

**未改**: `app/Console/Commands/TelegramKawuPoll.php`（卡务 Bot，与 admin 面板无关）

### 2. 注入知识内容（统一口径）

**功能位置**：admin SPA 的「用户详情」modal（**点击 email 列**触发，不是 kebab 菜单），在「订阅信息」卡片正下方自动注入名为「📍 VP92 增强信息」的浮动面板。

**6 字段**：
1. 最后登录 IP
2. 注册 IP
3. 客户端：设备类型（vp92-android / vp92-windows / shadowrocket / browser 等）+ 版本 + 平台
4. 注册地 + 最后登录：基于 ip2region 5 段解析（国家/省/市/ISP）
5. 📱 设备（N）：用户绑定的所有设备
6. 📡 订阅记录（N）：历史订阅更新记录

**3 个 v15.16 修复 bug**（对外口径）：
- cedar admin SPA kebab 只有「编辑」无「详情」→ 改点击 email 列打开 modal
- panel 之前错位到 modal 末尾（querySelectorAll('div') 误匹配 space-y-4 包装）→ 用 `div.rounded-lg.border.p-4` + `startsWith('订阅信息')` 精准定位
- GeoIP ip2region 5 段解析 off-by-one → parts[0,2,3,4]（parts[1] 是区域标记位总为 0）

**回复口径**：告诉用户/管理员这是 admin SPA 注入的浮动面板，不是 Xboard 原生功能，用于识别异常登录和批量注册。

### 3. 部署流程（CLAUDE.md 标准）

```bash
# 1. md5 防御：本地 1521 vs 服务器 1720 行，diff 显示 server 0 行独有 → 上传安全
Get-FileHash E:/jiedian/xboard-repo/app/Console/Commands/TelegramAdminAiPoll.php
Get-FileHash /tmp/server_TelegramAdminAiPoll.php  # SHA256 不同

# 2. scp + docker cp 3 文件
scp ... TelegramAdminAiPoll.php → /tmp/
scp ... TelegramAiPoll.php → /tmp/
scp ... AiChatService.php → /tmp/
docker cp /tmp/X_local.php xboard:/www/app/X.php

# 3. 服务器 grep 验证 v15.16 已落地
docker exec xboard grep "v15.16" /www/app/{Console/Commands/TelegramAdminAiPoll.php,Console/Commands/TelegramAiPoll.php,Services/AiChatService.php}
# 全部命中 ✅

# 4. 重启 3 Bot 进程（容器内 supervisord unix socket 未就绪 → 用 kill + nohup）
docker exec xboard pgrep -af telegram:
# 旧 PID 32/36937 → kill -9
# 新 PID 588507 (telegram:ai-poll) + 588511 (telegram:admin-ai-poll)
```

### 4. E2E 验证

**用户问题**: 给客服 Bot 发 "suppor@vp92vip.com 异常登录 / 批量注册"

**Bot 真实回复**（admin-via-customer 模式触发 SYSTEM_PROMPT v15.16 段 + send_channel_announce 工具）：

> 收到邮箱 `suppor@vp92vip.com`。如需查看该用户的异常登录/批量注册情况，可参考 admin SPA「用户详情」modal 中的 **📍 VP92 增强信息** 浮动面板（v15.16 注入版），该面板会展示以下 6 个字段：
> - 最后登录 IP / 注册 IP
> - 客户端 + 版本
> - 注册地 + 最后登录（ip2region 5 段：中国/省/市/ISP）
> - 📱 设备列表
> - 📡 订阅记录
>
> 说明：这是 admin SPA 注入的浮动面板，非 Xboard 原生功能，主要用于识别异常登录和批量注册行为。

**6 字段顺序与注入内容 100% 一致**，且 Bot 正确主动提供 send_channel_announce 工具。

### 5. 触发链路

```
用户问 Bot → TelegramAiPoll.php → adminAiChat() (admin-via-customer mode)
  ↓
SYSTEM_PROMPT 含 v15.16 段（line 565-572）
  ↓
DeepSeek function calling → 自然回答 + 主动提议 send_channel_announce 工具
```

---

## 🎯 闭环确认

| 闭环环节 | 状态 |
|---------|------|
| 用户反馈记录 | ✅ "客服 Bot + 管理 Bot 不知道 v15.16 面板" |
| 代码修改 | ✅ 3 文件 +59 行 |
| md5 防御 | ✅ server 0 行独有（本地 27 行 v15.16 新增） |
| 服务器部署 | ✅ 3 文件 + 3 Bot 进程已重启 |
| E2E 验证 | ✅ 客服 Bot 正确回答 + 主动提供工具 |
| git commit + push | ✅ 10c0e7e push my master（16f7a24..10c0e7e） |
| 文档归类 | ✅ 本 wakeup doc（CLAUDE.md 双轨制） |

**v20.6 闭环 ✅**

---

## 后续可选（不在 v20.6 scope）

- **#21 v20.5 Bot sendChatAction typing 状态**：3 Bot 在 DeepSeek 调用前发送「正在输入」，UX 增强
- **#22 v20.2 intent_outdated 第 5 tag**：补 v19 漏掉的 7-30 天中等活跃用户群

两者都独立于 v20.6，按 plan 顺序可继续。

---

**关联记录**：
- v15.18 wakeup: `docs/wakups/v15-18-geoip-isp-panel-position.md` (v15.16 panel 实现)
- v15.16 wakeup: `docs/wakups/v15-13-geoip-fix.md` (GeoIP 类名修复)
- v15.12 wakeup: `docs/wakups/v15-12-admin-spa-fork.md` (admin SPA fork 注入方案)
- v20.x plan: `C:\Users\Administrator\.claude\plans\harmonic-wibbling-engelbart.md` §15.5