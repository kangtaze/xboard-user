# v20.2 intent_outdated 第 5 tag — 完成入库

**日期**：2026-08-28
**状态**：✅ 完成 + 入库
**commit**：`b5d7d29` push 到 `my/master`（0bbd62d..b5d7d29）
**任务 ID**：#22

---

## 1. 目标

v19 完成了 4 tag 潜在付费用户识别，但漏掉一批**中段漏斗用户**——曾订阅但过期 7-30 天的用户。这些用户：
- 已经不是 churned_warm（过期 > 7 天）
- 不是 expiring_soon（不会很快流失）
- 不是 intent_cold（曾经付费过）
- **完全没有被任何 tag 覆盖 → 营销邮件漏发**

**方案**：新增第 5 tag `intent_outdated` = **曾订阅 + 过期 7-30 天 + 未续费**

---

## 2. 改动文件（6 个）

| 文件 | 改动 |
|------|------|
| `app/Models/UserIntent.php` | 加 `TAG_INTENT_OUTDATED` 常量 + ALL_TAGS 包含 5 tag |
| `app/Services/IntentClassifier.php` | `classify()` 加第 5 分支；`usersByTag()` 加 case |
| `app/Console/Commands/SendIntentCampaign.php` | TEMPLATE_MAP / SUBJECT_MAP / CONTENT_MAP 加 outdated |
| `app/Console/Kernel.php` | 加 `send:intent-campaign --tag=intent_outdated --force` 周二 10:00 cron |
| `tests/Feature/IntentClassifierTest.php` | 加 4 测试（10/10 通过） |
| `app/Console/Commands/IntentRefresh.php` | **顺带修复** pre-existing UNIQUE 冲突 bug |

### 2.1 UserIntent 新增

```php
public const TAG_INTENT_OUTDATED = 'intent_outdated';
public const ALL_TAGS = [
    self::TAG_EXPIRING_SOON,
    self::TAG_CHURNED_WARM,
    self::TAG_INTENT_HIGH,
    self::TAG_INTENT_COLD,
    self::TAG_INTENT_OUTDATED,  // v20.2
];
```

### 2.2 IntentClassifier::classify() 第 5 分支

```php
// 5. intent_outdated (v20.2): 曾订阅但过期 7-30 天 — 中段漏斗，4 tag 漏洞
if ($user->plan_id !== null
    && $user->expired_at !== null
    && $user->expired_at < $now - 7 * 86400
    && $user->expired_at > $now - 30 * 86400) {
    return UserIntent::TAG_INTENT_OUTDATED;
}
```

### 2.3 SendIntentCampaign 模板映射

```php
TEMPLATE_MAP: outdated → 'notify' (复用)
SUBJECT_MAP:  outdated → 'VP92 想您了 - 专属回归优惠等你领取'
CONTENT_MAP:  outdated → "您已经有一段时间没用了，VP92 想念您！...作为曾经的 VIP 用户..."
```

`in_array($tag, [TAG_INTENT_HIGH, TAG_INTENT_COLD, TAG_INTENT_OUTDATED])` 加 outdated 判断（notify 模板需要 content）

### 2.4 Kernel.php 新 cron

```php
$schedule->command('send:intent-campaign --tag=intent_outdated --force')
    ->weekly()->tuesdays()->at('10:00');  // 避开周一/三/五/日
```

### 2.5 IntentRefresh UNIQUE 冲突修复（顺带）

**Bug**：原实现 `DELETE WHERE last_mailed_at IS NULL` + `INSERT`，已发送过的 user 重新分类时 UNIQUE 冲突（v19 dry-run 时测试发送过 3 个 user，遗留 last_mailed_at 设值）

**Fix**：取 (user_id, last_mailed_at) 映射 → 删对应 (user_id, tag) 旧记录 → 插保留 last_mailed_at

```php
$existingMailed = DB::table('v2_user_intent')
    ->where('tag', $tag)->whereIn('user_id', $userIds)
    ->pluck('last_mailed_at', 'user_id')->toArray();

DB::table('v2_user_intent')
    ->where('tag', $tag)->whereIn('user_id', $userIds)->delete();

$rows = [];
foreach ($userIds as $userId) {
    $rows[] = [
        'user_id' => $userId,
        'tag' => $tag,
        'last_mailed_at' => $existingMailed[$userId] ?? null,  // 保留！
        ...
    ];
}
```

**未做**：测试覆盖（应该加 IntentRefreshTest，但 v20.2 工作量已超出预期）

---

## 3. TDD 流程

### 3.1 RED — 4 个新测试看失败

```bash
$ docker exec -w /www xboard phpunit tests/Feature/IntentClassifierTest.php
.﻿.﻿.﻿.﻿.﻿.﻿.E﻿.﻿.﻿E                                                        10 / 10 (100%)
There were 2 errors:
1) test_intent_outdated_returns_user_expired_7_to_30_days
   Error: Undefined constant App\Models\UserIntent::TAG_INTENT_OUTDATED
2) test_intent_outdated_usersByTag_returns_only_expired_7_to_30_days
   Error: Undefined constant App\Models\UserIntent::TAG_INTENT_OUTDATED
Tests: 10, Assertions: 9, Errors: 2.
```

✅ 失败原因正确：TAG_INTENT_OUTDATED 不存在

### 3.2 GREEN — 实现 5 文件改动后

```bash
$ docker exec -w /www xboard phpunit tests/Feature/IntentClassifierTest.php
.﻿.﻿.﻿.﻿.﻿.﻿.﻿.﻿.﻿.                                                        10 / 10 (100%)
OK (10 tests, 13 assertions)
```

✅ 10/10 通过，13 assertions

---

## 4. 端到端验证

### 4.1 intent:refresh 跑通（修 UNIQUE bug 后）

```bash
$ docker exec xboard php artisan intent:refresh
+-----------------+------------+
| Tag             | 匹配用户数 |
+-----------------+------------+
| expiring_soon   | 5          |
| churned_warm    | 23         |
| intent_high     | 0          |
| intent_cold     | 0          |
| intent_outdated | 73         |  ← v20.2 新增中段漏斗用户群
+-----------------+------------+
✅ 重算完成
```

✅ **73 个用户**进入 intent_outdated（v19 漏掉的真实中段漏斗）

### 4.2 send:intent-campaign --tag=intent_outdated --dry

```bash
$ docker exec xboard php artisan send:intent-campaign --tag=intent_outdated --dry
=== 处理 tag: intent_outdated ===
匹配用户: 73

+-----+----------------------+------+------------+
| ID  | Email                | Plan | Expired    |
+-----+----------------------+------+------------+
| 45  | test0703@vp92vip.com | 1    | 2026-07-31 |  ← 27.3 天前过期
| 63  | 290201734@qq.com     | 1    | 2026-08-10 |
| 64  | 673348868@qq.com     | 1    | 2026-08-10 |
| 74  | 15321577601@163.com  | 1    | 2026-08-13 |
| ...
+-----+----------------------+------+------------+
```

✅ 73 用户匹配，前 10 样本展示，过期日期全在 7-30 天范围

### 4.3 schedule:list 5 个 send cron 全在册

```bash
$ docker exec xboard php artisan schedule:list | grep intent
  30   0  * * *  php artisan intent:refresh ............... Next Due: 19小时后
  0    10 * * 1  php artisan send:intent-campaign --tag=intent_high --force
  0    10 * * 2  php artisan send:intent-campaign --tag=intent_outdated --force  ← v20.2 新增
  0    10 * * 3  php artisan send:intent-campaign --tag=intent_cold --force
  0    10 * * 5  php artisan send:intent-campaign --tag=churned_warm --force
  0    10 * * *  php artisan send:intent-campaign --tag=expiring_soon --force
```

✅ 5 个 send cron + intent:refresh 全部在册

### 4.4 模板验证（反射读 CONTENT_MAP）

```
TAG_INTENT_OUTDATED constant: intent_outdated
ALL_TAGS: expiring_soon, churned_warm, intent_high, intent_cold, intent_outdated

outdated content template:
您已经有一段时间没用了，VP92 想念您！
自从您上次订阅，VP92 已大幅升级：
• 节点数量翻倍，全球 50+ 国家覆盖
• 连接速度提升 30%，4K 视频秒开
• 新增多端同步，电脑/手机/平板无缝切换
当前版本：{{latest_version}}
{{latest_features}}
作为曾经的 VIP 用户，您可享受专属回归折扣：
{{promo_end_date}}
立即查看：{{url}}

outdated subject: VP92 想您了 - 专属回归优惠等你领取
outdated template_name: notify
```

✅ 模板映射正确

---

## 5. 部署流程

### 5.1 md5 对比（CLAUDE.md 必做）

```bash
$ ssh ... 'docker exec xboard md5sum /www/app/Models/UserIntent.php ... /www/tests/Feature/IntentClassifierTest.php'
bda54a351965d303f019017950018743  /www/app/Models/UserIntent.php
e16c8033a7178f5302a87b7f3af4a80e  /www/app/Services/IntentClassifier.php
4b687aa0061b355200a9ac0afbdf85bd  /www/app/Console/Commands/SendIntentCampaign.php
7824e50b8a6bc7a396b1214c918b4a3d  /www/app/Console/Kernel.php
7184347a48ced901141b7111ee6a5bff  /www/app/Console/Commands/IntentRefresh.php
4ac8c9eac5469eb4fa4edad3c0438751  /www/tests/Feature/IntentClassifierTest.php

# 本地（6/6 一致）
bda54a351965d303f019017950018743 *.../UserIntent.php
...
```

✅ 6/6 md5 一致

### 5.2 部署

```bash
# 4 文件上传 + docker cp + 部署
scp -i ... E:/jiedian/xboard-repo/Xboard/Xboard-master/app/Models/UserIntent.php root@:/tmp/
scp -i ... E:/jiedian/xboard-repo/Xboard/Xboard-master/app/Services/IntentClassifier.php root@:/tmp/
scp -i ... E:/jiedian/xboard-repo/Xboard/Xboard-master/app/Console/Commands/SendIntentCampaign.php root@:/tmp/
scp -i ... E:/jiedian/xboard-repo/Xboard/Xboard-master/app/Console/Kernel.php root@:/tmp/
scp -i ... E:/jiedian/xboard-repo/Xboard/Xboard-master/app/Console/Commands/IntentRefresh.php root@:/tmp/
scp -i ... E:/jiedian/xboard-repo/Xboard/Xboard-master/tests/Feature/IntentClassifierTest.php root@:/tmp/

ssh root@ ... 'docker cp /tmp/xxx.php xboard:/tmp/ && docker exec xboard cp /tmp/xxx.php /www/app/.../xxx.php'
```

### 5.3 服务器验证

```bash
$ docker exec xboard php artisan octane:reload
$ docker exec -w /www xboard phpunit tests/Feature/IntentClassifierTest.php
OK (10 tests, 13 assertions)
$ docker exec xboard php artisan schedule:list | grep intent
# 5 send + 1 refresh 全在册
$ docker exec xboard php artisan intent:refresh
# 73 intent_outdated 写入 v2_user_intent
```

---

## 6. 已知决策与遗留

### 6.1 决策点（已对齐）

- ✅ **不复用 intent_* 配置**：SendIntentCampaign 用全局 `intent_latest_version/intent_latest_features/intent_promo_end_date`，5 tag 共享。无需新增 `intent_outdated_*` config keys（plan §11.2 原计划有 5 个新 key，实际不需要）
- ✅ **不复用 intent_latest_version 但内容不同**：CONTENT_MAP 中 outdated 文案与 intent_cold 略有不同（强调"想念您"+"产品升级"+ "VIP 用户专属"），但用同一个全局 version/features 变量

### 6.2 已知遗留

- 🟡 **IntentRefreshTest 未写**：UNIQUE 冲突 fix 是用 PHP 手测验证（先看 bug 出现，再看 fix 后消失），无自动测试。**建议 v21 加**：`tests/Feature/IntentRefreshTest.php` 覆盖：① refresh 第二次跑不冲突；② refresh 保留 last_mailed_at
- 🟢 **未真发邮件**：当前仅 dry-run 验证 73 用户匹配 + 模板正确。真发触发待周二 10:00 cron 自然运行（用户可手动 `--force` 提前测）

---

## 7. 闭环检查

| 检查项 | 状态 |
|--------|------|
| 代码 md5 一致 | ✅ 6/6 |
| TDD RED → GREEN | ✅ 10/10 |
| intent:refresh 成功 | ✅ 73 intent_outdated |
| dry-run 模板/分类正确 | ✅ 73 匹配 |
| schedule:list 5 send cron 在册 | ✅ |
| octane:reload | ✅ |
| git commit + push my master | ✅ b5d7d29 |
| wakeup doc 创建 | ✅ 本文件 |
| session-progress 更新 | ⏳ 待更新 |

---

## 8. v20 路线图完成

| sub | 名称 | 状态 | commit |
|-----|------|------|--------|
| v20.1 | tracking 基建 | ✅ | 033efd1 |
| **v20.2** | **intent_outdated 第 5 tag** | ✅ | **b5d7d29 (本)** |
| v20.3 | Bot tool 集成（4 tool） | ✅ | 033efd1 |
| v20.4 | 周报自动推送 | ✅ | 16f7a24 |
| v20.5 | Bot typing 状态 | ✅ | b5e2cbc |
| v20.6 | Bot 知识 v15.16 面板 | ✅ | 10c0e7e/ed9f3ee |

**v20 计划 6/6 全部完成** 🎉

---

## 9. 下一步建议

v20 计划完成。后续可能方向（待用户决策）：

| 选项 | 内容 | 工作量 |
|------|------|--------|
| **A** | v21 新计划（用户提方向） | — |
| **B** | 补 IntentRefreshTest + 测试覆盖率 | ~30min |
| **C** | 周二 10:00 cron 真发前手动 --force 测试 | ~5min |
| **D** | 休息 / 等用户手机实测 typing | — |

按数字键或文字回复即可。