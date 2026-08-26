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
    // 2. DOM 观察: cedar SPA 不会自动调 getUserInfoById, 必须在 Radix UI 用户详情 modal 打开时主动拉
    const observer = new MutationObserver(() => {
      if (document.getElementById('vp92-geo-panel')) return;
      // Radix UI modal selector (max-w-xl + fixed + top-[50%])
      const modal = document.querySelector('div.fixed.left-\\[50\\%\\].top-\\[50\\%\\].max-w-xl') ||
                    Array.from(document.querySelectorAll('div')).find(d => {
                      const t = d.textContent || '';
                      return t.includes('基本信息') && t.includes('订阅信息') && t.includes('Token') && t.length < 12000;
                    });
      if (!modal) return;
      const txt = modal.textContent || '';
      // 匹配 "用户ID: 328" 或 "用户ID：328" (Chinese full-width colon)
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
      // 用户要求：面板直接嵌入到用户详情 modal 内（不漂浮在角落）
      // Radix UI 不会清掉 portal 外手动 append 的节点
      const old = document.getElementById('vp92-geo-panel');
      if (old) old.remove();
      // 找当前用户详情 modal (Radix fixed top-[50%])
      const modal = document.querySelector('div.fixed.left-\\[50\\%\\].top-\\[50\\%\\].max-w-xl');
      const target = modal || document.body;
      const panel = document.createElement('div');
      panel.id = 'vp92-geo-panel';
      // 内嵌样式: 占据 modal 内整宽，无 fixed/z-index（modal 自带定位）
      panel.style.cssText = 'margin-top:16px;padding:14px 16px;background:#f8fafc;color:#0f172a;border:1px solid #e2e8f0;border-radius:8px;font-family:system-ui;font-size:13px;line-height:1.7';
      panel.innerHTML = `
        <h4 style="margin:0 0 10px;font-size:14px;color:#0f3460;border-bottom:1px solid #e2e8f0;padding-bottom:6px">📍 VP92 增强信息 (v15.12) #${esc(user.id)}</h4>
        <div><b>最后登录IP:</b> ${esc(user.last_login_ip_str || long2ip(user.last_login_ip))}</div>
        <div><b>注册IP:</b> ${esc(user.register_ip_str || long2ip(user.register_ip))}</div>
        <div><b>客户端:</b> ${esc(user.last_login_client && user.last_login_client.type)} ${esc(user.last_login_client && user.last_login_client.version)} / ${esc(user.last_login_client && user.last_login_client.os)}</div>
        <div><b>注册地:</b> ${geo(user.register_geo)}</div>
        <div><b>最后登录:</b> ${geo(user.last_login_geo)}</div>
        <details style="margin-top:8px"><summary style="cursor:pointer;color:#0f3460">📱 设备 (${user.devices ? user.devices.length : 0})</summary><div style="margin-top:6px;padding-left:12px">${(user.devices || []).map(d => '<div style="padding:3px 0;border-bottom:1px dashed #e2e8f0">' + esc(d.browser) + ' / ' + esc(d.os) + ' / ' + esc(d.country) + ' / ' + fmt(d.last_used_at) + '</div>').join('') || '<div style="color:#64748b">无</div>'}</div></details>
        <details><summary style="cursor:pointer;color:#0f3460">📡 订阅记录 (${user.subscription_logs ? user.subscription_logs.length : 0})</summary><div style="margin-top:6px;padding-left:12px">${(user.subscription_logs || []).map(s => '<div style="padding:3px 0;border-bottom:1px dashed #e2e8f0">' + esc(s.client_type) + ' ' + esc(s.client_version) + ' / ' + esc(s.country) + ' / ' + fmt(s.created_at) + '</div>').join('') || '<div style="color:#64748b">无</div>'}</div></details>
      `;
      target.appendChild(panel);
    }
    function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function geo(g) { if (!g) return '-'; var parts = []; if (g.country) parts.push(g.country); if (g.province) parts.push(g.province); if (g.city) parts.push(g.city); return parts.length ? parts.join(' / ') : 'IPv6 暂不支持'; }
    function fmt(ts) { return ts ? new Date(ts * 1000).toLocaleString() : '-'; }
    function long2ip(n) { if (!n || isNaN(n)) return '-'; n = parseInt(n); if (n < 0 || n > 0xFFFFFFFF) return '-'; return ((n >>> 24) & 0xFF) + '.' + ((n >>> 16) & 0xFF) + '.' + ((n >>> 8) & 0xFF) + '.' + (n & 0xFF); }
  })();
  </script>
</body>

</html>
