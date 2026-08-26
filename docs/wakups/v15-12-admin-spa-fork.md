# v15.12 — Admin SPA 5字段 JS 注入方案（C-0 已决定，未实施）

> **接续**：v15.11 后端方案 B1 完成入库（commit cc580f2）+ v15.12 调研完成（commit 71d8ab6）
> **本会话决策**：C-0 JS 注入方案（用户认可，下个会话直接实施不做小测试）
> **状态**：方案已定，待实施

## ⚡ 一句话状态

```
v15.11 ✅ 后端: commit cc580f2 (5 字段在 UserController::transformUserData)
v15.12 调研 ✅ commit 71d8ab6 (10 项关键发现 + 阻塞点: cedar 无 SPA 源码)
v15.12 实施 ⏳ C-0 JS 注入方案已定 (admin.blade.php +70 行, 浮动面板)
```

## 🎯 已决策方案（用户认可）

**C-0 JS 注入方案** — 不 fork cedar SPA，直接在 admin.blade.php 注入 JS bundle，浮动面板挂在用户详情 modal 旁边。

**用户原话（决策依据）**：
- "本来用户详情页 就是读取，又不需要写入什么东西"
- "你注入进去不就可以了吗"
- "下个新窗口直接开始不做小测试"

## 📋 实施清单（一次性做完，不做最小验证）

### 改动文件
- `/www/resources/views/admin.blade.php`（容器路径）—— 等于 `E:\jiedian\xboard-repo\Xboard\Xboard-master\resources\views\admin.blade.php`
- 加 ~70 行：Alpine.js CDN + fetch 拦截 + 浮动面板渲染

### 实施步骤（不再验证，直接做）

1. **修改本地 admin.blade.php** 加注入脚本：
   ```blade
   {{-- Alpine.js CDN --}}
   <script defer src="https://unpkg.com/[email protected]/cdn.min.js"></script>
   
   {{-- v15.12 v15.11 5 字段浮动面板 (C-0 JS 注入) --}}
   <script>
   (function() {
     const origFetch = window.fetch;
     window.fetch = async function(...args) {
       const url = (args[0] instanceof Request) ? args[0].url : args[0];
       const res = await origFetch.apply(this, args);
       if (typeof url === 'string' && url.includes('getUserInfoById')) {
         res.clone().json().then(d => {
           if (d && d.data) renderPanel(d.data);
         });
       }
       return res;
     };
     function renderPanel(user) {
       const old = document.getElementById('vp92-geo-panel');
       if (old) old.remove();
       const panel = document.createElement('div');
       panel.id = 'vp92-geo-panel';
       panel.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;width:420px;max-height:80vh;overflow:auto;background:#1e293b;color:#e2e8f0;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.4);padding:20px;font-family:system-ui;font-size:13px';
       panel.innerHTML = `
         <button onclick="this.parentElement.remove()" style="position:absolute;top:8px;right:12px;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:18px">×</button>
         <h3 style="margin:0 0 12px;font-size:15px">📍 5字段详情 (v15.12)</h3>
         <div style="margin-bottom:6px"><b>客户端:</b> ${esc(user.last_login_client?.type)} ${esc(user.last_login_client?.version)} / ${esc(user.last_login_client?.os)}</div>
         <div style="margin-bottom:6px"><b>注册地:</b> ${geo(user.register_geo)}</div>
         <div style="margin-bottom:6px"><b>最后登录:</b> ${geo(user.last_login_geo)}</div>
         <details style="margin-top:8px"><summary style="cursor:pointer">📱 设备 (${user.devices?.length||0})</summary><div style="margin-top:6px">${(user.devices||[]).map(d=>`<div style="padding:4px;border-bottom:1px solid #334155">${esc(d.browser)} / ${esc(d.os)} / ${esc(d.country)} / ${fmt(d.last_used_at)}</div>`).join('')||'<div style="color:#64748b">无</div>'}</div></details>
         <details><summary style="cursor:pointer">📡 订阅记录 (${user.subscription_logs?.length||0})</summary><div style="margin-top:6px">${(user.subscription_logs||[]).map(s=>`<div style="padding:4px;border-bottom:1px solid #334155">${esc(s.client_type)} ${esc(s.client_version)} / ${esc(s.country)} / ${fmt(s.created_at)}</div>`).join('')||'<div style="color:#64748b">无</div>'}</div></details>
       `;
       document.body.appendChild(panel);
     }
     function esc(s) { return (s==null?'':String(s)).replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c])); }
     function geo(g) { if(!g) return '-'; return [g.country,g.province,g.city].filter(Boolean).join(' / ')||'IPv6 暂不支持'; }
     function fmt(ts) { return ts ? new Date(ts*1000).toLocaleString() : '-'; }
   })();
   </script>
   ```

2. **修改前必做**（按 CLAUDE.md 避坑）：
   ```bash
   # 拉服务器最新版本
   ssh root@154.3.38.212 "docker exec xboard cat /www/resources/views/admin.blade.php" > /tmp/server_admin_blade.php
   md5sum /tmp/server_admin_blade.php Xboard/Xboard-master/resources/views/admin.blade.php
   # 不一致则先合并再改
   ```

3. **部署**：
   ```bash
   scp Xboard/Xboard-master/resources/views/admin.blade.php root@154.3.38.212:/tmp/admin_blade_local.php
   ssh root@154.3.38.212 "docker cp /tmp/admin_blade_local.php xboard:/www/resources/views/admin.blade.php && docker exec xboard php artisan view:clear && docker exec xboard php artisan cache:clear && docker exec xboard php artisan octane:reload"
   ```

4. **浏览器实测**（用户要求不做小测试，但必须浏览器实测确认）：
   - 登录 https://vp92vip.com/2d532679
   - 进用户列表 → 点用户详情
   - 确认右下角浮动面板出现且显示 5 字段
   - chrome-devtools 截图

5. **git commit + push**（立即入库）：
   ```bash
   cd E:/jiedian/xboard-repo
   git add Xboard/Xboard-master/resources/views/views/admin.blade.php
   # 注意: 实际路径是 Xboard/Xboard-master/resources/views/admin.blade.php
   git commit -m "feat(admin): v15.12 JS 注入浮动面板显示 5 字段 (C-0 方案)"
   git -c http.proxy= push my master
   ```

## 📂 关键文件位置

| 路径 | 用途 |
|------|------|
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\resources\views\admin.blade.php` | 本地 Blade 入口（待修改） |
| `/www/resources/views/admin.blade.php` | 容器内 Blade 入口 |
| `E:\jiedian\xboard-repo\Xboard\Xboard-master\app\Http\Controllers\V2\Admin\UserController.php` | admin transformUserData 5 字段（cc580f2 已入库） |
| `/www/public/assets/admin/assets/index-CEIYH7i8.js` | cedar SPA dist bundle（不修改） |

## 🚨 已知坑

1. **cedar SPA 用 fetch + React Query** — 我们拦截 window.fetch 一定能拿到响应（fetch 是 native API）
2. **浮动面板用 position: fixed** — 脱离 React virtual DOM，不会被 React 重渲染清掉
3. **Alpine.js 用 CDN 加载** — 失败时浮动面板不显示但不影响 cedar SPA（降级安全）
4. **md5 必查** — admin.blade.php 可能本地落后于服务器

## 🔧 备用方案（如 C-0 失败）

如 fetch 拦截失效或浮动面板被遮：
- 改用 MutationObserver 监听 [role="dialog"] 元素
- 改用 Modal 内 portal（z-index 不够时）
- 退回到方案 C-2（新 Blade 页 + 浮动按钮点击）

## 🚨 关联记忆

- [[v15-11-admin-geoip-subscription-log]] — v15.11 后端进度
- [[feedback-rigor-no-fluff]] — 验证先于完成（用户明确说"不做小测试"=信任已完成调研）
- [[tool-selection-rules]] — 读本地文件用 Read
- [[xboard-theme-system]] — theme:: 命名空间
- [[xboard-caddyfile-config-location]] — Caddyfile active 位置