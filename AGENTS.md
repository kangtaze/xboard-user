# AGENTS.md

This file provides guidance for AI coding agents working on the VP92 ecosystem (multiple repos under `E:\jiedian`).
Read by Cursor / Codex / Copilot / Claude Code and other AI coding agents that auto-discover AGENTS.md upward.

## ⚠️ AI 工作风格约定（所有 AI 工具必读！）

用户在 VP92 项目投入大量时间 + 资金，对错误容忍度低。**这条工作风格不靠"看起来对"判断，必须用证据**：

1. **验证先于完成** — 每个动作都用证据确认（`git status` / `aapt dump badging` / `protobuf varint` / `md5sum` / `grep` 实际输出），不靠"应该成了"。例子：v1.2.6 AAB 构建后用 Python protobuf varint 解 `base/manifest/AndroidManifest.xml` 验证 `2026076440` 存在，并确认错误的 `2026074439`/`2026076439` 都不在
2. **避坑优先于速度** — 修改前先读对应子项目的 AGENTS.md/CLAUDE.md + PROJECT_STATUS.md 致命避坑；尤其：
   - 改后端 PHP 前必拉服务器最新版 md5 对比（防本地旧代码覆盖线上）
   - AAB/Android versionCode 修改后必须还原 base
   - wrangler r2 必须 `--remote`（默认上传到本地 0 字节实例）
   - Inno Setup `{{autopf}}` 双花括号必须渲染成单花括号
   - 改文件用 Python 而非 `sed`（破坏反斜杠）
   - **blade 部署铁律**：`/www/public/theme/VP92/` 在容器外 (host) 和容器内是**不同物理路径**（docker overlayfs），host ls/md5 验证会误导（v22/v23 教训，v24 明确化），必须用 `curl -sI https://vp92vip.com/theme/VP92/assets/<hash>.js` 或 `docker exec xboard ls -la /www/public/theme/VP92/assets/` 验证。详见 `E:\jiedian\CLAUDE.md` 「⚠️ `/www/public/theme/VP92/` 容器外 vs 容器内」段
3. **拒绝默认 AI 行为** — 不为了"显得有帮助"而 hallucination；不为了"显得主动"而抢跑（用户未明确要求时不要开工）；不为了"显得周到"而加未要求的内容
4. **不被流行做法带偏** — 不模仿 Cursor/Codex 的"先猜再改"模式；遇到不确定（如 git remote 是 `origin` 还是 `my`、代理端口是 7890 还是 1080）必须先核查（`git remote -v` / `git config --global --get-all http.proxy` / `env | grep -i proxy`），不凭训练印象推断
5. **知识边界主动声明** — 对 `E:\jiedian` 下其他子项目（vp92-flclash / xboard-repo / vp92-frontend / edgetunnel-extracted / OpenClaw 值班系统 / vp92-diagnostics-worker 等）只有本文件 + 各子项目 AGENTS.md/CLAUDE.md + Claude Code 记忆里的碎片认知，拿不准先读再判断，不凭碎片印象下结论

**触发条件**：任何回答/操作前先想"这个结论我有证据吗？"没证据就先验证再回答/操作；用户没要求的事不做，哪怕你觉得"顺手应该做"。

详细背景：见 Claude Code 私有记忆 `vp92-work-style-rigor-no-fluff.md` 与 `vp92-work-style-plan-first.md`。

## 项目结构（jiedian 下所有子项目）

| 子项目 | 路径 | Git 仓库 | 子项目主文档 |
|--------|------|---------|------------|
| **vp92-flclash**（FlClash fork，APP 端） | `E:\jiedian\vp92-flclash\` | github.com/kangtaze/vp92.git | `AGENTS.md` + `CLAUDE.md` + `PROJECT_STATUS.md` |
| **xboard-repo**（Xboard fork，VPN 后端） | `E:\jiedian\xboard-repo\` | github.com/kangtaze/xboard-ze.git | `CLAUDE.md` |
| **vp92-frontend**（VP92 Vue SPA） | `E:\jiedian\vp92-frontend\` | — | — |
| **edgetunnel-extracted**（CF Worker 订阅） | `E:\jiedian\edgetunnel-extracted\` | — | — |
| **xboard-theme-lighttrack**（Vue3 自定义主题） | `E:\jiedian\xboard-theme-lighttrack\` | — | — |
| **xboard-deploy**（部署脚本与参考副本） | `E:\jiedian\xboard-deploy\` | — | — |
| **docs**（设计文档与规格说明） | `E:\jiedian\docs\` | — | — |
| **PC-vp92**（遗留 fork，VP92 桌面端增强） | `E:\jiedian\PC-vp92\` | — | 已停用，构建统一在 vp92-flclash |

每个子项目有自己的 `AGENTS.md`/`CLAUDE.md`（如果存在），记录该子项目特定的指令。本文件只放跨子项目通用约定。

## 服务器 & 网络

详见 `E:\jiedian\CLAUDE.md`（Claude Code 自动加载；其他 AI 工具可手动读）：

- HK 服务器：`root@154.3.38.212` (DMIT)，Docker 容器 `xboard`
- 域名：vp92vip.com / vp92.app / vp92vip.cc.cd
- SSH：`ssh -i C:/Users/Administrator/.ssh/id_ed25519 root@154.3.38.212`

**网络环境**：路由器透明代理（无本地代理软件）；`git push` 前必查 `git config --global http.proxy`，如设了 `127.0.0.1` 先 `--unset`；push 不稳定时 sleep 5-15 重试。

## 跨项目维护清单（改一处必查另一处）

| 触发改动 | 必须同步更新 |
|---------|------------|
| `vp92-flclash/lib/common/navigation.dart` 底部导航增删改 | 服务器端 AI 客服知识库的「APP 界面导览」段 |
| `vp92-flclash/lib/views/account/account.dart` 功能卡增删改 | 服务器端 AI 客服知识库「APP 界面导览」段 |
| `vp92-flclash/lib/common/error_codes.dart` 错误码增删改 | `E:\jiedian\docs\ai-error-knowledge.md` 对照表 |
| `E:\jiedian\xboard-repo\app\Services\AiChatService.php` system prompt 改 | 同步 `E:\jiedian\AiChatService-server.php` 基准 |

详细同步流程见各子项目 CLAUDE.md/AGENTS.md。

## 代码-文档双轨制（修改任何代码必须配套文档归类）

**历史背景**：用户经历过服务器崩溃，需要从文档恢复系统 — 因此「代码即文档」不可靠，必须配套归类文档作为单一事实来源。

### 触发条件

修改任何代码文件（.php / .js / .dart / .kt / .swift / .go / .py / .yaml / .json / .sh / .blade.php / .vue 等）或附属文件（.env / docker-compose / nginx / systemd / cron / 数据库 migration / 路由配置）时，**必须配套维护归类文档**。

### 必须做的（每个修改触发 3 件事）

1. **文件 header 注释** — 新建或修改任何代码文件，必须在头部或第一个函数前写明：
   - **用途**：一句话说清这文件干什么
   - **调用方**：谁会 import / require / include 它
   - **被调用方**：它依赖哪些文件 / 服务 / API / 数据库表
   - **关联记录**：关联 issue / commit / 文档链接

2. **项目级归类文档** — 每个项目维护一份 `docs/INDEX.md`：
   - 所有源文件清单（按目录 / 功能 / 优先级分类）
   - **关系树**：模块之间调用关系 + 数据流（关键路径）
   - 入口文件说明

3. **关键服务崩溃恢复手册** — 服务类项目（Xboard 后端 / CF Worker / Telegram Bot / OpenClaw 值班系统）必须配套 `docs/recovery.md`：
   - 从 0 重建系统的步骤
   - 含版本号 / commit hash / 配置位置 / 启动命令 / 数据库 schema
   - 不依赖记忆 / 口口相传 / git reflog 之外的任何东西

### 关系树示例（Xboard 设备限制修复链）

```
用户登录 → AuthService::generateAuthData() → 无 X-Device-Id?
                                              ├─ 是 + UA 含 Mozilla → token name 加 web: 前缀（计数豁免）
                                              └─ 否 → 正常计数
                          ↓
LoginService B2 计数时排除 web:% → 旧版 APP 显示「旧版客户端」正常计数
```

### 关键约束（不可妥协）

- **文档与代码同步**：同一 commit / 同一 PR，不允许分两次
- **关系树是单一事实来源**：不靠"我记得是这样"
- **崩了能修**：仅靠 `docs/` 就能恢复系统
- **附属文件也算**：`.env.example` / `docker-compose.yml` / nginx / systemd / cron 都必须有文档解释为什么这么配置、跟哪个服务绑定

### 已写入位置

- `C:\Users\Administrator\.claude\CLAUDE.md`（用户全局）
- `E:\jiedian\vp92-flclash\AGENTS.md`（项目级）
- 完整背景见 Claude Code 私有记忆 `vp92-code-docs-registry.md`