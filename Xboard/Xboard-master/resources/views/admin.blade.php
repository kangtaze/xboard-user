<!DOCTYPE html>
<html lang="en">

<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0" />
  <title>{{ $title }}</title>
  <script>
    window.settings = {
      base_url: "/",
      title: "{{ $title }}",
      version: "{{ $version }}",
      logo: "{{ $logo }}",
      secure_path: "{{ $secure_path }}",
    };
  </script>
  @php
    $manifestPath = public_path('assets/admin/manifest.json');
    $manifest = file_exists($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
    $entry = is_array($manifest) ? ($manifest['index.html'] ?? null) : null;
    $scripts = [];
    $styles = [];
    $locales = [];

    if (is_array($entry)) {
      $visited = [];
      $collectAssets = function ($chunkName) use (&$collectAssets, &$manifest, &$visited, &$scripts, &$styles) {
        if (isset($visited[$chunkName]) || !isset($manifest[$chunkName]) || !is_array($manifest[$chunkName])) {
          return;
        }

        $visited[$chunkName] = true;
        $chunk = $manifest[$chunkName];

        if (!empty($chunk['css']) && is_array($chunk['css'])) {
          foreach ($chunk['css'] as $cssFile) {
            $styles[$cssFile] = $cssFile;
          }
        }

        if (!empty($chunk['imports']) && is_array($chunk['imports'])) {
          foreach ($chunk['imports'] as $import) {
            $collectAssets($import);
          }
        }

        if (!empty($chunk['isEntry']) && !empty($chunk['file'])) {
          $scripts[$chunk['file']] = $chunk['file'];
        }
      };

      $collectAssets('index.html');
    }

    foreach (glob(public_path('assets/admin/locales/*.js')) ?: [] as $localeFile) {
      $locales[] = 'locales/' . basename($localeFile);
    }
    sort($locales);
  @endphp

  @if($entry && count($scripts) > 0)
    @foreach($styles as $css)
      <link rel="stylesheet" crossorigin href="/assets/admin/{{ $css }}" />
    @endforeach
    @foreach($locales as $locale)
      <script src="/assets/admin/{{ $locale }}"></script>
    @endforeach
    @foreach($scripts as $js)
      <script type="module" crossorigin src="/assets/admin/{{ $js }}"></script>
    @endforeach
  @else
    {{-- Fallback: hardcoded paths for backward compatibility --}}
    <script type="module" crossorigin src="/assets/admin/assets/index.js"></script>
    <link rel="stylesheet" crossorigin href="/assets/admin/assets/index.css" />
    <link rel="stylesheet" crossorigin href="/assets/admin/assets/vendor.css">
    <script src="/assets/admin/locales/en-US.js"></script>
    <script src="/assets/admin/locales/zh-CN.js"></script>
    <script src="/assets/admin/locales/ko-KR.js"></script>
  @endif
</head>

<body>
  <div id="root"></div>

  {{-- v15.12 v15.11 5 字段浮动面板 (C-0 JS 注入 + Radix DOM 观察主动触发) --}}
  <script>
  (function() {
    const origFetch = window.fetch;
    // 1. fetch 拦截: 命中 getUserInfoById 或 /user/info 时渲染面板
    window.fetch = async function(...args) {
      const url = (args[0] instanceof Request) ? args[0].url : args[0];
      const res = await origFetch.apply(this, args);
      if (typeof url === 'string' &&
          (url.includes('getUserInfoById') || url.includes('/user/info'))) {
        try {
          const data = await res.clone().json();
          if (data && data.data && (data.data.id || data.data.email)) renderPanel(data.data);
        } catch (e) {}
      }
      return res;
    };
    // 2. DOM 观察: cedar SPA 不会自动调 getUserInfoById, 必须在 Radix UI 用户详情 sheet 打开时主动拉
    const observer = new MutationObserver(() => {
      if (document.getElementById('vp92-geo-panel')) return;
      // v15.15: cedar 用户详情是 Radix Sheet (右侧滑出面板), 不是 centered modal
      // 类: fixed ... inset-y-0 right-0 h-full w-3/4 border-l sm:max-w-md
      // 用 role="dialog" + data-state="open" 通用选择器
      const sheet = document.querySelector('div[role="dialog"][data-state="open"]');
      if (!sheet) return;
      const txt = sheet.textContent || '';
      // 匹配 "用户ID: 328" 或 "用户ID：328" 或 "用户ID: #328"
      const idMatch = txt.match(/用户?ID[：::\s#]*(\d{2,})/) || txt.match(/user[_\s-]*id[：::\s#]*(\d{2,})/i);
      if (!idMatch) return;
      const id = idMatch[1];
      const sp = (window.settings && window.settings.secure_path) || '2d532679';
      const bu = (window.settings && window.settings.base_url) || '/';
      // cedar SPA token 存在 XBOARD_ACCESS_TOKEN localStorage 键 (JSON {value: "Bearer xxx"})
      let token = '';
      try {
        const raw = localStorage.getItem('XBOARD_ACCESS_TOKEN') || '{}';
        const parsed = JSON.parse(raw);
        token = (parsed.value || '').replace(/^Bearer\s+/i, '');
      } catch (err) {}
      origFetch(bu + 'api/v2/' + sp + '/user/getUserInfoById?id=' + id, {
        headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
      })
      .then(r => r.json())
      .then(d => { if (d && d.data && d.data.id) renderPanel(d.data); })
      .catch(() => {});
    });
    observer.observe(document.body, { childList: true, subtree: true });

    function renderPanel(user) {
      // v15.16: 用 class 选择器 div.rounded-lg.border.p-4 + MutationObserver 等卡片渲染
      const old = document.getElementById('vp92-geo-panel');
      if (old) old.remove();
      // v15.18+: 同时清理旧的邀请人卡片, 避免 geo-panel 重建时 invite-panel 成孤儿
      const oldInvite = document.getElementById('vp92-invite-panel');
      if (oldInvite) oldInvite.remove();
      const sheet = document.querySelector('div[role="dialog"][data-state="open"]');
      if (!sheet) { document.body.appendChild(makePanel(user)); makeInvitePanel(user); return; }
      // Card 容器: div.rounded-lg.border.p-4 (cedar 用户详情卡片通用类)
      // 用 startsWith 防止匹配到嵌套子元素或大容器
      const findSubInfoCard = () => Array.from(sheet.querySelectorAll('div.rounded-lg.border.p-4')).find(d => {
        const t = (d.textContent || '').trim();
        return t.startsWith('订阅信息');
      });
      const insertPanel = () => {
        const card = findSubInfoCard();
        const panel = makePanel(user);
        if (card && card.parentElement) {
          card.parentElement.insertBefore(panel, card.nextSibling);
        } else {
          sheet.appendChild(panel);
        }
      };
      insertPanel();
      // 如果 panel 落到 sheet 末尾 (cards 还没渲染完), MutationObserver 等候再移
      const cardObserver = new MutationObserver(() => {
        const existing = document.getElementById('vp92-geo-panel');
        if (!existing || existing.parentElement === sheet.querySelector('div.rounded-lg.border.p-4:last-of-type')) return;
        // 检查 panel 是否在最后一个 card 后面 (即 fallback 位置)
        const lastCard = sheet.querySelector('div.rounded-lg.border.p-4:last-of-type');
        if (lastCard && existing.previousElementSibling === lastCard) {
          const card = findSubInfoCard();
          if (card && card.parentElement) {
            card.parentElement.insertBefore(existing, card.nextSibling);
            cardObserver.disconnect();
          }
        }
      });
      cardObserver.observe(sheet, { childList: true, subtree: true });
      setTimeout(() => cardObserver.disconnect(), 3000);
      // v15.18+: 在 geo-panel 之后挂邀请人卡片
      makeInvitePanel(user);
    }
    function makePanel(user) {
      const panel = document.createElement('div');
      panel.id = 'vp92-geo-panel';
      panel.style.cssText = 'margin-top:16px;padding:14px 16px;background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0;border-radius:8px;font-family:system-ui;font-size:13px;line-height:1.7';
      panel.innerHTML = `
        <h4 style="margin:0 0 10px;font-size:14px;color:#0f3460;border-bottom:1px solid #e2e8f0;padding-bottom:6px">📍 VP92 增强信息 (v15.16) #${esc(user.id)}</h4>
        <div><b>最后登录IP:</b> ${esc(user.last_login_ip_str || long2ip(user.last_login_ip))}</div>
        <div><b>注册IP:</b> ${esc(user.register_ip_str || long2ip(user.register_ip))}</div>
        <div><b>客户端:</b> ${esc(user.last_login_client && user.last_login_client.type)} ${esc(user.last_login_client && user.last_login_client.version)} / ${esc(user.last_login_client && user.last_login_client.os)}</div>
        <div><b>注册地:</b> ${geo(user.register_geo)}</div>
        <div><b>最后登录:</b> ${geo(user.last_login_geo)}</div>
        <details style="margin-top:8px"><summary style="cursor:pointer;color:#0f3460">📱 设备 (${user.devices ? user.devices.length : 0})</summary><div style="margin-top:6px;padding-left:12px">${(user.devices || []).map(d => '<div style="padding:3px 0;border-bottom:1px dashed #e2e8f0">' + esc(d.browser) + ' / ' + esc(d.os) + ' / ' + esc(d.country) + ' / ' + fmt(d.last_used_at) + '</div>').join('') || '<div style="color:#64748b">无</div>'}</div></details>
        <details><summary style="cursor:pointer;color:#0f3460">📡 订阅记录 (${user.subscription_logs ? user.subscription_logs.length : 0})</summary><div style="margin-top:6px;padding-left:12px">${(user.subscription_logs || []).map(s => '<div style="padding:3px 0;border-bottom:1px dashed #e2e8f0">' + esc(s.client_type) + ' ' + esc(s.client_version) + ' / ' + esc(s.country) + ' / ' + fmt(s.created_at) + '</div>').join('') || '<div style="color:#64748b">无</div>'}</div></details>
      `;
      return panel;
    }
    // v15.18+: 邀请人独立卡片, 挂在 vp92-geo-panel 之后
    function makeInvitePanel(user) {
      const old = document.getElementById('vp92-invite-panel');
      if (old) old.remove();
      const panel = document.createElement('div');
      panel.id = 'vp92-invite-panel';
      panel.style.cssText = 'margin-top:12px;padding:14px 16px;background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0;border-radius:8px;font-family:system-ui;font-size:13px;line-height:1.7';
      const inv = user.invite_user;
      const hasInvite = inv && inv.id;
      panel.innerHTML = `
        <h4 style="margin:0 0 10px;font-size:14px;color:#0f3460;border-bottom:1px solid #e2e8f0;padding-bottom:6px">📨 邀请信息 #${esc(user.id)}</h4>
        <div><b>邀请人:</b> ${hasInvite ? esc(inv.email) : '<span style="color:#64748b">无 (直接注册)</span>'}</div>
        <div><b>邀请人ID:</b> ${hasInvite ? esc(inv.id) : '<span style="color:#64748b">-</span>'}</div>
      `;
      // 挂到 vp92-geo-panel 之后; 没有 geo-panel 则追加到 sheet 末尾
      const geoPanel = document.getElementById('vp92-geo-panel');
      if (geoPanel && geoPanel.parentElement) {
        geoPanel.parentElement.insertBefore(panel, geoPanel.nextSibling);
      } else {
        const sheet = document.querySelector('div[role="dialog"][data-state="open"]');
        if (sheet) sheet.appendChild(panel);
      }
      return panel;
    }
    function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function geo(g) { if (!g) return '-'; var parts = []; if (g.country) parts.push(g.country); if (g.province) parts.push(g.province); if (g.city) parts.push(g.city); if (g.isp) parts.push(g.isp); return parts.length ? parts.join(' / ') : 'IPv6 暂不支持'; }
    function fmt(ts) { return ts ? new Date(ts * 1000).toLocaleString() : '-'; }
    function long2ip(n) { if (!n || isNaN(n)) return '-'; n = parseInt(n); if (n < 0 || n > 0xFFFFFFFF) return '-'; return ((n >>> 24) & 0xFF) + '.' + ((n >>> 16) & 0xFF) + '.' + ((n >>> 8) & 0xFF) + '.' + (n & 0xFF); }
  })();
  </script>
</body>

</html>
