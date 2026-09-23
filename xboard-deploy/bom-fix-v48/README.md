# v48.1 Baked-in Fixes Backup

> ⚠️ **接手这个项目必读**: 这是 xboard 容器 baked-in 镜像层的关键修复备份,**任何接手项目的人都要看这个目录**。如果遇到 `/2d532679/edgetunnel` 或 `/2d532679/cf-worker` 返回 404,或者 Laravel schedule 不跑,或者 bot 静默死,**先查这里**。不是代码 bug,大概率是 baked-in 镜像层的修复丢了 — docker cp 这 3 个文件 + restart 就好。

## 这个目录干什么

保存 xboard 容器 **baked-in 镜像层** 的 3 个关键修复 + 1 个完整 Docker 镜像,**容器重建/服务器迁移后可以用这里的内容恢复**。

| 文件 | 修什么 | 历史 |
|------|--------|------|
| `start.sh` | php artisan tinker 输出 BOM 前缀,start.sh 加 Python `lstrip(b'\xef\xbb\xbf')` 清掉 | v48.1 (2026-09-22) |
| `supervisord.conf` | 镜像层缺 `[program:schedule]` + 3 bot 缺 startsecs=5/startretries=10/exitcodes=0/stopwaitsecs=15 | v35b + v46 (2026-09-05 + 2026-09-15) |
| `Caddyfile` | caddy 反代配置,目前未改(只是备份,防意外) | v48 (2026-09-22) |
| `xboard-v48.1-bom-fix.tar` | 完整 docker commit 镜像导出(612MB 压缩),含上面 3 个修复 | v48.1 (2026-09-23) |

## 容器里这些文件在哪

```
/www/.docker/caddy/start.sh                              (v48.1 BOM stripper)
/etc/supervisor/conf.d/supervisord.conf                  (v35b schedule + v46 startsecs)
/etc/caddy/Caddyfile                                     (caddy 反代配置)
```

**关键**: 这 3 个路径都在 **Docker 镜像 baked-in 层**,**不是 bind mount**。容器重建后,如果用官方 `:latest` 镜像起,这些文件会回到未修复版本 → `/2d532679/edgetunnel` 404 / schedule 不跑 / bot 静默死。

## 当前 md5 (2026-09-23 备份时)

```
84890de073bea81b5544650e5f6961ee  start.sh
f341623748ae994c180b85916c813cd7  supervisord.conf
<caddyfile md5>                                              Caddyfile
355fb67c74999944688e4113f067d22e  xboard-v48.1-bom-fix.tar (612MB)
```

## 灾难恢复步骤 (按场景选)

### 场景 A: 容器重建,但服务器还在,Windows 备份目录也在

最快,用 `.tar` 直接起新容器:
```bash
# 1. 加载镜像
docker load -i xboard-v48.1-bom-fix.tar
# 2. 停掉旧容器,起新容器(用加载的镜像 tag)
docker stop xboard && docker rm xboard
docker run -d --name xboard -p 80:80 -p 7001:7001 \
  <原启动参数> \
  ghcr.io/kangtaze/xboard:v48.1-bom-fix
# 3. 验证
curl -sI https://vp92vip.com/2d532679/edgetunnel   # 期望 200 OK
curl -sI https://vp92vip.com/2d532679/cf-worker    # 期望 200 OK
docker exec xboard supervisorctl status schedule   # 期望 RUNNING
```

### 场景 B: 容器重建 + 服务器还在,但没有 `.tar`(只本地目录备份)

手动 docker cp 3 个文件 + restart:
```bash
# 1. 启动新容器(官方原版)
docker run -d --name xboard -p 80:80 -p 7001:7001 ... \
  ghcr.io/cedar2025/xboard:latest

# 2. 恢复 baked-in 修复
docker cp ./start.sh xboard:/www/.docker/caddy/start.sh
docker exec xboard chmod +x /www/.docker/caddy/start.sh
docker cp ./supervisord.conf xboard:/etc/supervisor/conf.d/supervisord.conf
docker cp ./Caddyfile xboard:/etc/caddy/Caddyfile  # 如果改了

# 3. 重启 supervisord + caddy
docker exec xboard kill -HUP 1
docker exec xboard pkill caddy
docker exec xboard supervisorctl restart caddy

# 4. 验证(同上)
```

### 场景 C: 服务器炸了,Windows 也炸了,什么都没了

**事前预防**:
- ✅ 这个目录应该推到 GitHub private repo 或 OneDrive/网盘(异地备份)
- ✅ 如果 docker image 推到 ghcr.io 成功,新服务器 `docker pull ghcr.io/kangtaze/xboard:v48.1-bom-fix` 即可

**事后无解**,只能从 GitHub 历史重建:
1. 找 `kangtaze/xboard-ze` 仓库的 `bom-fix-v48/` 分支或 release
2. 找 `vp92-v48-1-edgetunnel-cf-worker-404-bom-secure-path.md` memory 重建 start.sh
3. 找 `vp92-v35-trial-marketing.md` 重建 supervisord.conf 的 schedule 段
4. 找 `vp92-v46-kawu-ai-silent-death-supervisord-fix.md` 重建 startsecs 段

## 状态 (2026-09-24 v50)

- ✅ **本地完整备份已保存**: 本目录 (`E:\jiedian\xboard-deploy\bom-fix-v48\`),含 4 文件 (612MB .tar)
- ✅ **服务器原始 docker commit tag** (作者 namespace): `ghcr.io/cedar2025/xboard:v48.1-bom-fix` (ID `d8cbfe5692e0`, 1.91GB) — 服务器本地 daemon,仅原作者命名空间可读
- ✅ **本地 .tar 已推 GitHub release**: [kangtaze/xboard-ze@v48.1-bom-fix](https://github.com/kangtaze/xboard-ze/releases/tag/v48.1-bom-fix),asset 611MB (640777216 bytes),state=uploaded,gh 隐含 hash 校验通过
- ✅ **镜像已推 ghcr.io (Private)**: `ghcr.io/kangtaze/xboard:v48.1-bom-fix` (re-tag from cedarc2025,Private,digest `sha256:d8cbfe5692e0...`) — [kangtaze packages](https://github.com/kangtaze?tab=packages)
- ⚠️ **当前容器仍在跑**: `xboard` 用 `:latest` 镜像,继续服务 (未切到 v48.1-bom-fix)
- ✅ **P0 异地备份全闭环**: Windows 炸了(从 GitHub release 拉 tar)/ 服务器炸了(从 ghcr.io pull 镜像,需 PAT)/ 全炸了(从 GitHub release 拉 tar + docker load) 都能恢复

## 验证 baked-in 状态(改文件前必做)

改 `/www/.docker/caddy/start.sh` 或 `/etc/supervisor/conf.d/supervisord.conf` 前:
```bash
docker inspect xboard --format '{{range .Mounts}}{{println .Source, .Destination, .Type}}{{end}}'
# 看 /www/.docker 和 /etc/supervisor/conf.d 是 bind mount 还是 overlayfs
# overlayfs → 容器重建会丢修复
```

## 决策待办 (v50)

- [ ] **favicon.ico 404** (v48 遗留小问题) — 加个空 favicon 或 `<link rel="alternate icon">`
- [ ] **重建 SOP 文档化到 CLAUDE.md「重建检查清单」段**

## 接手人必读 5 件事

1. **404 优先查这里** — `/2d532679/edgetunnel` 或 `/2d532679/cf-worker` 返回 404,99% 是 caddy 的 `SECURE_PATH` 环境变量有问题,99% 是 baked-in start.sh 没 BOM stripper → 用 `start.sh` + `supervisord.conf` 覆盖 + restart caddy
2. **schedule 没跑查这里** — `crontab -l` 显示空,`supervisorctl status schedule` 显示 FATAL → supervisord.conf 缺 `[program:schedule]` 段 → docker cp 覆盖 + `kill -HUP 1`
3. **bot 静默死查这里** — 容器内 `docker exec xboard ps auxf | grep telegram` 看到进程在,但 supervisor 状态不对 → supervisord.conf 缺 startsecs/startretries/exitcodes/stopwaitsecs → docker cp 覆盖 + `kill -HUP 1`
4. **改 baked-in 文件前必查** — `docker inspect` 看 mounts,如果是 overlayfs 改完必须 docker commit 或覆盖修复
5. **每次修复后必更新本 README** — md5 + 灾难恢复步骤,这是给下一个接手人的唯一指南

## 关联 memory

- `vp92-v48-1-edgetunnel-cf-worker-404-bom-secure-path.md` — BOM 根因
- `vp92-v35-trial-marketing.md` — schedule 缺失
- `vp92-v46-kawu-ai-silent-death-supervisord-fix.md` — startsecs 缺失
- `vp92-v48-2-reuse-existing-email-template-not-rebuild.md` — 邮件流程教训