# VP92极速加速器 — 最新项目进度

> **最后更新**: 2026-08-19
> **状态**: 🔍 排查在线用户上报问题（老版本能看到，新版本看不到）

---

## 🔴 当前排查中：在线用户上报问题

### 现象
- **老版本** Worker 部署后，后台能看到用户 `netzege@163.com` 在线 ✅
- **新版 worker-merged.js** 部署后，后台看不到用户在线 ❌
- 新版本已回滚到老版本，当前线上正常

### 已排除
- API 调用完全一致：两个版本都调 `/api/v1/server/UniProxy/push`（diff 确认无差异）
- 两个版本的 `reportXboardTraffic` 函数相同
- `XBOARD_NODE_ID`、`XBOARD_TOKEN`、`XBOARD_ORIGIN` 配置相同

### 已确认
- Xboard V1 API `/api/v1/server/UniProxy/alive` 是专用的在线上报接口
  - 参数：`{ userId: [ip] }` — 直接 POST，不需要 KV，不需要 cron
  - 对应 `ServerService::processAlive()` → `DeviceStateService->setDevices()`
- Xboard V1 API `/api/v1/server/UniProxy/push` 上报流量时**也会**设置 `SERVER_{TYPE}_ONLINE_USER` 缓存
  - 但只设置 push 中的用户数（单次 disconnect 的用户），不是总在线数
- 老版本 Worker 的 `scheduled` cron handler 是**死代码**（CF 上没有 cron trigger）
- `xboard-reporter` Worker 在 CF 上**不存在**（API 只查到 vp92-doh、worker01、worker06）
- `xboard-alive` KV **没有人写入**（KV sync 也不写）

### **待查明**（新窗口第一个任务）
- 老版本到底怎么实现在线用户显示的？
  - 可能方向1：检查老 Worker 的 `push` 数据格式是否有不同
  - 可能方向2：检查 Xboard `processPush` 的完整逻辑，看 push 是否真的设置了在线缓存
  - 可能方向3：CF Dashboard 查看 worker01 的实时日志（wrangler tail），看老版本是否有其他 API 调用
  - 可能方向4：对比老版本和新版本的 `getXboardUserId` 调用是否成功（如果 userId 解析失败，push 不会被调用）

### 下一步方案
- **方案 A**：在 Worker 连接成功后直接调 `/api/v1/server/UniProxy/alive?token=X&node_id=Y` 上报在线
  - 不需要 KV
  - 不需要 cron trigger
  - 断开时发送 `{ userId: [] }` 清除
- **方案 B**：查明白老版本为什么能工作，再针对性修复

---

## ✅ 已完成：新版 Worker 模板合并（2026-08-19）

### 合并内容（`worker-merged.js` → 服务器 `/www/storage/app/worker-template.js`）
| 模块 | 说明 |
|------|------|
| **用户 UUID 验证** | KV user-list 缓存(5min TTL) + Xboard API `/api/v2/server/user/check` 实时验证 |
| **流量上报** | `reportXboardTraffic` V1 API，上传/下载字节跟踪，连接关闭时上报 |
| **版本接口安全优化** | SHA-256 + timingSafeEqual 替代弱求和校验 |

### ⚠️ 合并时添加的在线追踪代码（需清理或替换）
- `recordAlive()` / `removeAlive()` — KV 方案（**不可行，没人写 KV**）
- `scheduled` cron handler — 读取 KV 上报（**不可行，没 cron trigger**）
- wrangler.jsonc 添加的 cron trigger 配置（**不需要了如果用方案 A**）
- **这些改动需要回滚**，改用方案 A

### 关键文件
| 文件 | 说明 |
|------|------|
| `C:\Users\Administrator\Desktop\wok\worker-merged.js` | 合并版（含需清理的 KV 在线追踪代码） |
| `C:\Users\Administrator\Desktop\wok\server-worker-template.js` | 老版备份（在线追踪正常） |
| `C:\Users\Administrator\Desktop\wok\worker.js` | 纯新版原文件（327,451 bytes，无 XBOARD） |

---

## ✅ 已完成：XHTTP 协议分析（2026-08-19）

### 结论
- **Worker 已内置 XHTTP 支持**，改 config `传输协议: "xhttp"` 即可
- **Xboard 已完全支持** `EdgetunnelServiceProvider::parseUpstreamSubscription()` 自动从上游检测 `type=xhttp`，5 分钟缓存
- **SingBox 格式不支持 XHTTP**（节点会被静默排除）
- **不需要改任何代码**，用户自己改 Worker config 即可

---

## ✅ 已完成：Nginx 废弃配置清理（2026-08-19）
- 删除 `/etc/nginx/conf.d/auth-proxy.conf`（指向废弃域名 `ferrari.ccwu.cc`）

---

## ✅ 已完成：Google Play Badge 自托管（2026-08-19）
- Badge 放到 `/www/public/img/google-play-badge.png`
- 落地页改为引用 `/img/google-play-badge.png`

---

## ✅ 已完成：AI客服动态知识库注入（2026-08-18）

---

## 📋 VIP1-VIP3 域名配置总览

| | VIP1 | VIP2 | VIP3 |
|---|---|---|---|
| **订阅域名** | `vp92.app/s/{token}`（三组共用） | 同左 | 同左 |
| **子节点域名** | `vp92.cc.cd` | `vp92vip2.cc.cd` | `vp92vip3.cc.cd` |
| **Worker 路由** | `vp92.cc.cd` | `vp92vip2.cc.cd` | `vp92vip3.cc.cd` |
| **XBOARD_ORIGIN** | `dns.vp92vip.cc.cd` | `dns.vp92vip.cc.cd` | `dns.vp92vip.cc.cd` |
| **group_id** | 2 | 3 | 4 |
| **server id** | 113 | 114 | 115 |

---

## 📋 Xboard 在线上报 API 详解

### V1 API（推荐）

**流量上报**: `POST /api/v1/server/UniProxy/push?token=X&node_id=Y`
- Body: `{ userId: [upload_bytes, download_bytes] }`
- 副作用：也会设置 `SERVER_{TYPE}_ONLINE_USER_{nodeId}` 缓存（TTL 3600s）

**在线设备上报**: `POST /api/v1/server/UniProxy/alive?token=X&node_id=Y`
- Body: `{ userId: [ip1, ip2] }`
- 调用 `ServerService::processAlive()` → `DeviceStateService->setDevices()`

**在线列表**: `GET /api/v1/server/UniProxy/alivelist?token=X&node_id=Y`
- 返回当前在线设备

**在线连接数**: `POST /api/v1/server/UniProxy/online?token=X&node_id=Y`
- Body: `{ userId: connectionCount }`
- 设置 `USER_ONLINE_CONN_{type}_{nodeId}_{userId}` 缓存

### V2 API（老版 cron 用的）
- `POST /api/v2/server/report` — 接受 `{ token, node_id, traffic, alive, online }`
- `alive` → 调 `processAlive`，`online` → 调 `processOnline`

### Server Model 在线属性
- `$server->online` → 读取缓存 `SERVER_{TYPE}_ONLINE_USER_{nodeId}`，默认 0
- `$server->is_online` → 节点是否在线（1/0）

---

## 📋 在线追踪相关代码位置

| 文件 | 行号 | 说明 |
|------|------|------|
| `worker-merged.js` | 3-15 | `getXboardUserId()` — 通过 UUID 查 Xboard userId |
| `worker-merged.js` | 17-30 | `reportXboardTraffic()` — 流量上报 V1 push |
| `worker-merged.js` | 32-52 | `recordAlive()` / `removeAlive()` — ⚠️ KV 方案（需替换） |
| `worker-merged.js` | ~1282 | gRPC 路径 tracker 创建 + recordAlive 调用 |
| `worker-merged.js` | ~1794 | WS 路径 tracker 创建 + recordAlive 调用 |
| `worker-merged.js` | ~1903 | WS close handler + removeAlive 调用 |
| `worker-merged.js` | ~623 | scheduled cron handler（需替换为方案 A） |
| `ServerService_final_decoded.php` | 235-241 | `processAlive()` — 处理 alive 上报 |
| `ServerService_final_decoded.php` | 246-260 | `processOnline()` — 处理 online 上报 |
| `ServerService_final_decoded.php` | 226-227 | `processPush()` — 流量上报也设置 ONLINE_USER 缓存 |
| `UniProxyController.php` | 83-91 | `alive()` endpoint — 接收 alive 数据 |
| `UniProxyController.php` | 72-81 | `alivelist()` endpoint — 返回在线列表 |
| `EdgetunnelServiceProvider.php` | 163-188 | `detectSubscriptionConfigs()` — 上游订阅协议检测 |

---

## 唤醒提示词（直接复制粘贴到新窗口）

```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。里面包含了当前状态、已完成的工作、待处理事项和精确的技术细节。按照文件中的“当前状态摘要”部分开始工作。
```

---
## 当前状态摘要（新窗口快速上下文，读完进度文件后看这个）

**紧急任务**: 排查在线用户上报问题。老版本 Worker 能在 Xboard 后台显示用户在线，新版不行。两个版本的 push API 调用完全一致（diff 确认）。待查明老版本到底怎么实现的。

**已排除**: API 调用一致、KV/cron 不是原因（老版也没有）、xboard-reporter 不存在。

**待查方向**: 老版 push 数据格式是否有差异？getXboardUserId 是否在新版中失败？直接用方案 A（连接时调 V1 alive API）绕过问题。

**注意**: worker-merged.js 里我加了 KV 方案的在线追踪代码（recordAlive/removeAlive/scheduled handler），这些需要**回滚并替换为方案 A**（直接调 V1 alive API，不需要 KV 和 cron）。