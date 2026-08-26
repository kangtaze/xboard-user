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
      const old = document.getElementById('vp92-geo-panel');
      if (old) old.remove();
      const panel = document.createElement('div');
      panel.id = 'vp92-geo-panel';
      panel.style.cssText = 'position:fixed;bottom:20px;right:20px;z-index:99999;width:420px;max-height:80vh;overflow:auto;background:#1e293b;color:#e2e8f0;border-radius:12px;box-shadow:0 10px 40px rgba(0,0,0,0.4);padding:20px;font-family:system-ui;font-size:13px';
      panel.innerHTML = `
        <button onclick="this.parentElement.remove()" style="position:absolute;top:8px;right:12px;background:none;border:none;color:#94a3b8;cursor:pointer;font-size:18px">×</button>
        <h3 style="margin:0 0 12px;font-size:15px">📍 5字段详情 (v15.12) #${esc(user.id)}</h3>
        <div style="margin-bottom:6px"><b>邮箱:</b> ${esc(user.email)}</div>
        <div style="margin-bottom:6px"><b>客户端:</b> ${esc(user.last_login_client && user.last_login_client.type)} ${esc(user.last_login_client && user.last_login_client.version)} / ${esc(user.last_login_client && user.last_login_client.os)}</div>
        <div style="margin-bottom:6px"><b>注册地:</b> ${geo(user.register_geo)}</div>
        <div style="margin-bottom:6px"><b>最后登录:</b> ${geo(user.last_login_geo)}</div>
        <details style="margin-top:8px"><summary style="cursor:pointer">📱 设备 (${user.devices ? user.devices.length : 0})</summary><div style="margin-top:6px">${(user.devices || []).map(d => '<div style="padding:4px;border-bottom:1px solid #334155">' + esc(d.browser) + ' / ' + esc(d.os) + ' / ' + esc(d.country) + ' / ' + fmt(d.last_used_at) + '</div>').join('') || '<div style="color:#64748b">无</div>'}</div></details>
        <details><summary style="cursor:pointer">📡 订阅记录 (${user.subscription_logs ? user.subscription_logs.length : 0})</summary><div style="margin-top:6px">${(user.subscription_logs || []).map(s => '<div style="padding:4px;border-bottom:1px solid #334155">' + esc(s.client_type) + ' ' + esc(s.client_version) + ' / ' + esc(s.country) + ' / ' + fmt(s.created_at) + '</div>').join('') || '<div style="color:#64748b">无</div>'}</div></details>
      `;
      document.body.appendChild(panel);
    }
    function esc(s) { return (s == null ? '' : String(s)).replace(/[&<>"']/g, function (c) { return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]; }); }
    function geo(g) { if (!g) return '-'; var parts = []; if (g.country) parts.push(g.country); if (g.province) parts.push(g.province); if (g.city) parts.push(g.city); return parts.length ? parts.join(' / ') : 'IPv6 暂不支持'; }
    function fmt(ts) { return ts ? new Date(ts * 1000).toLocaleString() : '-'; }
  })();
  </script>
</body>

</html>
