# 📌 v25 Wakeup Snapshot — 2026-09-02 blade overlayfs 铁律沉淀 + locales 边界决策

> **当前最新进度**（v25 完成 🎉）
> **会话类型**：v24 候选 → 选「#3 + #5 文档先行」
> **状态**：✅ CLAUDE.md + AGENTS.md 文档更新 + memory 入库 (待 git commit)
> **完整 v25 细节**: MEMORY `vp92-v25-blade-overlayfs-locales-boundary.md`

---

## ⚡ 一句话状态总结

```
v25 状态（2026-09-02）：
✅ CLAUDE.md 新增「⚠️ /www/public/theme/VP92/ 容器外 vs 容器内」段 (line 33-55)
   - Docker overlayfs 行为明确化
   - 正确验证: curl -sI 或 docker exec 内 ls
   - 错误验证: host ls/md5 (v22/v23 教训)
✅ CLAUDE.md 新增「vp92-frontend 边界」段 (line 66-84)
   - locales 决策: 维持 src/locales/* 4 文件不入库
   - 决策依据 + 灾难恢复路径 + 改动标准流程
✅ AGENTS.md「避坑优先于速度」加一条踩坑 (line 17)
   - blade 部署铁律 (引用 CLAUDE.md 详细段)
✅ Session memory 入库 (vp92-v25-blade-overlayfs-locales-boundary.md)
✅ MEMORY.md 索引更新 (v25 entry 加顶部)
⏳ 待 git commit + push my master
```

---

## ⚠️ 重要决策记录

### v25 决策：locales 维持不入库

**为什么这样决定**：
- locale 改动频繁（每次 i18n 改动 50KB+ diff 噪音）
- v24 已验证：3 auth pages i18n 化 + zh-TW 新增 599 行全不入库，部署 + e2e 9/9 全部正常
- vp92-frontend 仅 tracked 16 文件，边界清晰

**跨会话同步靠**：
- session memory（`vp92-v24-auth-i18n-full.md` 记录了所有 locale 增量）
- 新会话首次 commit 不要 `git add src/locales/`

**灾难恢复路径**：
1. 从 session memory 重建（不是 git history，因为不入库）
2. 手动从 vue-vendor chunk 反编译 t() key（不推荐，容易漏）

---

## 📁 文件改动（2 tracked + 2 memory）

### 入库（2 文件）
| 文件 | 改动 |
|------|------|
| `E:\jiedian\CLAUDE.md` | 新增 2 段（line 33-55 overlayfs 铁律 + line 66-84 locales 边界） |
| `E:\jiedian\AGENTS.md` | 「避坑优先于速度」加一条踩坑（line 17） |

### memory（2 文件，不入 jiedian 仓库）
- `~/.claude/projects/E--jiedian/memory/vp92-v25-blade-overlayfs-locales-boundary.md` — 完整 v25 改动 + 决策记录
- `~/.claude/projects/E--jiedian/memory/MEMORY.md` — v25 entry 加到顶部

---

## 🚪 下次会话唤醒

```
读取 E:\jiedian\docs\session-progress-latest.md，这是最新项目进度文件。
里面包含了当前状态、已完成的工作（v25 文档先行 + blade overlayfs 铁律 + locales 边界决策）、
待处理事项和精确的技术细节。按 v25 候选决定下一步。
```

**最新 commit**: `3b03970 feat(i18n): 3 auth 页面整页 i18n 化 + zh-TW locale 上线`（v24，待 v25 commit）
**当前 master HEAD**: `3b03970` ✅
**远端同步**: `my/master` = `3b03970` ✅

**Wakeup 完。** 下次会话直接读这个文件就能恢复上下文。

---

## 📜 v22→v25 演化路径

| 版本 | commit | 类型 | 范围 | 详情 |
|------|--------|------|------|------|
| v22 | `4ffebd7` | feature | 邀请码跨页面保持 | MEMORY `invite-code-cross-page-persistence.md` |
| v23 | `50ed411` | fix (i18n) | RegisterPage 邀请码 placeholder | MEMORY `vp92-v23-invite-placeholder-i18n.md` |
| v24 | `3b03970` | feat (i18n) | 3 auth pages + zh-TW locale | MEMORY `vp92-v24-auth-i18n-full.md` |
| v25 | 待 commit | docs | blade overlayfs 铁律 + locales 边界 | MEMORY `vp92-v25-blade-overlayfs-locales-boundary.md` |

---

## 🔗 关联引用

- **v24 前置**: commit `3b03970 feat(i18n): 3 auth 页面整页 i18n 化 + zh-TW locale 上线`
- **v25 Memory**: `~/.claude/projects/E--jiedian/memory/vp92-v25-blade-overlayfs-locales-boundary.md`
- **Memory 索引**: `~/.claude/projects/E--jiedian/memory/MEMORY.md`
- **CLAUDE.md 铁令**: 「代码-文档双轨制」「git 备份常规」「严谨 + 拒绝敷衍」「不抢跑」
- **Windows git push**: 需 `-c http.sslBackend=openssl`

---

## 🎯 v26 候选（v25 已完成 #3 + #5，剩 #1/#2/#4）

1. **AI 客服 widget i18n 化** — `AI客服` 按钮硬编码中文（snapshot 行 34），不在 auth pages 范围
2. **`<title>` 标签 i18n** — `dashboard.blade.php line 9` 写死 `'VP92极速加速器'`，应跟 locale 切换
3. ~~**blade 部署铁律写入 memory**~~ — ✅ v25 已完成
4. **其他 Vue 页面 i18n** — nav / landing / tutorial / dashboard / nodes / plans / orders / checkout / tickets / invite / giftcard / settings / notifPanel（locales 已经全部填好，待 Vue 文件 t() 化）
5. ~~**locales 边界决策**~~ — ✅ v25 已完成（维持不入库）
