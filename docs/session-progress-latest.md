# 📌 v27 Wakeup Snapshot — 2026-09-03 CLAUDE.md theme/ overlayfs 铁律扩展

> **当前最新进度**（v27 完成 🎉）
> **会话类型**：v26 候选 → 选 #4 单独先做（止血）+ 备选 v27b 决策已留
> **状态**：✅ CLAUDE.md 文档扩展完成 + commit + memory 入库（push 待续）
> **完整 v27 细节**: MEMORY `vp92-v27-claude-md-theme-overlayfs.md`

---

## ⚡ 一句话状态总结

```
v27 状态（2026-09-03）：
✅ CLAUDE.md 新增「⚠️ /www/theme/VP92/ 容器外 vs 容器内」段（v25 public/ 段后 +47 行）
   - v26 实战踩坑 4 步过程（ssh cp 改 host → curl 旧 hash → 网站挂 404）
   - 正确部署 blade 文件 4 步流程（docker cp + docker exec cp）
   - 错误示例 + 铁律总结 + 验证部署三档
✅ 「前端部署流程」第 5/6/7 步同步改 docker cp 流程（-3 行 +3 行）
✅ commit b2762dc 入库（git commit 成功）
⏳ push my master 待续（v26 a9cb919 + v27 b2762dc 都在本地 ahead，network Recv failure）
✅ memory 入库（vp92-v27-claude-md-theme-overlayfs.md + MEMORY.md 索引）
✅ v27b 决策：<title> i18n 用服务端 Laravel __() 渲染，留作 v28+ 做
🚨 0 代码改动，纯文档止血，避免下个会话再踩 v26 同样的坑
```

---

## 📁 文件改动

### 入库 (1 文件)
- `CLAUDE.md` (+50 行 -3 行 = 净增 47 行)
  - 新增段：`### ⚠️ /www/theme/VP92/ 容器外 vs 容器内不同物理路径（v26 新发现！）`
  - 修正段：`### 前端部署流程（VP92 Vue SPA）` 第 5/6/7 步

### 不入库 (边界维持)
- 无

---

## 🚨 v26 踩坑复盘（驱动 v27 决策）

**v25 文档盲点**：v25 只覆盖 `/www/public/theme/VP92/` overlayfs 行为，**没覆盖** `/www/theme/VP92/` 同样的问题。

**v26 实战过程**（浪费 ~10 分钟 + 网站挂 404）：
1. `ssh cp` 改 host 视角的 `dashboard.blade.php` → host 文件已变
2. `curl vp92vip.com/dashboard` 仍返回 **旧 hash** → 网站挂
3. `docker exec xboard grep` 发现容器内 blade 还是旧版
4. 必须 `docker cp` + `docker exec cp` 才生效

**v27 修复**：CLAUDE.md 扩展 theme/ 段，铁律 = 「`vp92-frontend/theme/` 目录任何 blade 必须 docker cp + docker exec cp」。

---

## 🚪 下次会话唤醒

```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。
里面包含了当前状态（v27 完成，CLAUDE.md theme/ overlayfs 铁律扩展）、
待处理事项（push 重试 + v28 候选）和精确技术细节。
按 v28 候选决定下一步。
```

**最新 commit**: `b2762dc docs(i18n): CLAUDE.md 扩展 theme/ overlayfs 铁律 — v26 踩坑沉淀 v27`
**当前 master HEAD**: `b2762dc` ✅ (本地)
**远端同步**: ⏳ 待 push（v26 a9cb919 + v27 b2762dc 都在本地 ahead，network Recv failure 已 6 次）
**重试命令**: `git -c http.sslBackend=openssl push my master`

**Wakeup 完。** 下次会话直接读这个文件就能恢复上下文。

---

## 📜 v22→v27 演化路径

| 版本 | commit | 类型 | 范围 | 详情 |
|------|--------|------|------|------|
| v22 | `4ffebd7` | feature | 邀请码跨页面保持 | MEMORY `invite-code-cross-page-persistence.md` |
| v23 | `50ed411` | fix (i18n) | RegisterPage 邀请码 placeholder | MEMORY `vp92-v23-invite-placeholder-i18n.md` |
| v24 | `3b03970` | feat (i18n) | 3 auth pages + zh-TW locale | MEMORY `vp92-v24-auth-i18n-full.md` |
| v25 | `0f639df` | docs | blade overlayfs 铁律 (public/) + locales 边界 | MEMORY `vp92-v25-blade-overlayfs-locales-boundary.md` |
| v26 | `a9cb919` | feat (i18n) | AppSidebar 完整 i18n + overlayfs 铁律扩展 (theme/) | MEMORY `vp92-v26-sidebar-i18n.md` |
| v27 | `b2762dc` | docs | CLAUDE.md theme/ overlayfs 段（v26 踩坑沉淀）| MEMORY `vp92-v27-claude-md-theme-overlayfs.md` |

---

## 🔗 关联引用

- **v26 前置**: commit `a9cb919 feat(i18n): AppSidebar 完整 i18n 化 (10 处 + nav.aiChat/logout keys) v26`
- **v27 Commit**: `b2762dc docs(i18n): CLAUDE.md 扩展 theme/ overlayfs 铁律 — v26 踩坑沉淀 v27`
- **v27 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v27-claude-md-theme-overlayfs.md`
- **v26 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v26-sidebar-i18n.md`
- **v25 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v25-blade-overlayfs-locales-boundary.md`
- **Memory 索引**: `~/.claude/projects/E--jiedian/memory/MEMORY.md`
- **CLAUDE.md 铁令**: 「代码-文档双轨制」「git 备份常规」「严谨 + 拒绝敷衍」「不抢跑」
- **Windows git push**: 需 `-c http.sslBackend=openssl`

---

## 🎯 v28 候选（v27 已完成 theme/ 铁律，剩 #2 + #3）

1. **`<title>` 标签 i18n** — `dashboard.blade.php line 9/23/30` 写死 `'VP92极速加速器'` + `window.settings.title` 暴露
   - **v27b 决策**：服务端用 Laravel `__()` 渲染（最小侵入，Vue i18n 不动）
   - **工作量**：小（dashboard.blade.php 1 文件改动 + Laravel resources/lang 加 zh-CN/en-US/zh-TW）
   - **风险**：中（需验证 locale 切换时 title 跟随，且 controller 传 locale）

2. **其他 Vue 页面 i18n** — 3 文件 ~40 处硬编码：
   - `PlanPurchase.vue` — period labels (/月 /季 /半年 /年 /2年 /3年) + features 列表 (~10 处)
   - `CheckoutDialog.vue` — 支付方式（支付宝/微信）+ period labels + 错误消息 (~15 处)
   - `Orders.vue` — 列头（订单号/套餐/金额/支付方式/状态/创建时间/操作）+ period + 错误 (~15 处)
   - **工作量**：大（3 文件全 i18n + locales 增量）
   - **风险**：低（v22-v26 已验证模式）

3. **CLAUDE.md v27 铁律扩展**（如需要）— 如果 push 失败 持续，可考虑降级 push 频率

---

## ⚠️ 已知遗留事项

| 项 | 状态 | 影响 | 下次处理 |
|---|------|------|---------|
| v26 push `a9cb919` | ⏳ 待重试 | 远端落后 1 commit | 任意时段重试 |
| v27 push `b2762dc` | ⏳ 待重试 | 远端落后 2 commit | 任意时段重试 |
| `git config --global http.proxy` | ✅ 已 unset (本次会话) | push 不受影响 | 监控 |

---

## 🧪 验证

- ✅ git diff 校验：CLAUDE.md +50 -3，新段插入位置正确，无意外删除
- ✅ 新段关键术语覆盖：v26 + docker cp + docker exec cp + overlayfs + 铁律
- ✅ 「前端部署流程」第 5/6/7 步同步反映 docker cp 流程
- ✅ v25 public/ 段未动（避免重写历史教训）
- ⏳ push 验证未完成（network Recv failure 持续）