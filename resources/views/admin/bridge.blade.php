<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $title ?? 'Xboard' }} - 跳转中...</title>
    <style>
        body {
            background: #0f172a;
            color: #e2e8f0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0;
        }
        .spinner {
            display: inline-block;
            width: 32px;
            height: 32px;
            border: 3px solid #334155;
            border-top-color: #6366f1;
            border-radius: 50%;
            animation: spin 0.8s linear infinite;
        }
        @keyframes spin { to { transform: rotate(360deg); } }
        .text { margin-top: 16px; font-size: 14px; color: #94a3b8; }
        .err { color: #f87171; font-size: 13px; margin-top: 12px; }
        a.fallback { color: #818cf8; text-decoration: underline; font-size: 13px; display: inline-block; margin-top: 16px; }
    </style>
</head>
<body>
<div style="text-align:center">
    <div class="spinner"></div>
    <div class="text">{{ $message ?? '正在跳转...' }}</div>
    <div id="err" class="err" style="display:none"></div>
    <a id="fallback" class="fallback" style="display:none" href="#">点这里跳转</a>
</div>
<script>
(function() {
    // 安全配置：服务器注入的目标路径 + SPA 登录 URL
    const TARGET_PATH = @json($target_path ?? '/');
    const SPA_SIGNIN_URL = @json($spa_signin_url ?? '/');
    const TIMEOUT_MS = 4000;

    function showError(msg, href) {
        const e = document.getElementById('err');
        const a = document.getElementById('fallback');
        if (e) { e.textContent = msg; e.style.display = 'block'; }
        if (a && href) { a.href = href; a.style.display = 'inline-block'; }
    }

    function go(url) {
        try { window.location.replace(url); } catch(e) { window.location.href = url; }
    }

    // 1. 读 SPA 写入的 localStorage（兼容 SPA 与旧版 login.blade.php 两种存储格式）
    let rawToken = null;
    try {
        const stored = localStorage.getItem('XBOARD_ACCESS_TOKEN');
        if (stored) {
            const parsed = JSON.parse(stored);
            if (parsed && parsed.value) {
                rawToken = parsed.value;
            }
        }
    } catch (e) {
        // JSON parse error or storage disabled
    }

    if (rawToken) {
        // 把 token 通过 _t query 传给 Blade 路由；服务端会剥 Bearer 前缀并验证
        const sep = TARGET_PATH.includes('?') ? '&' : '?';
        go(TARGET_PATH + sep + '_t=' + encodeURIComponent(rawToken));
    } else {
        // 没 token：去 SPA 登录页，登录完成后 SPA 会跳回 target_path
        const sep = SPA_SIGNIN_URL.includes('?') ? '&' : '?';
        const redirectUrl = SPA_SIGNIN_URL + sep + 'redirect=' + encodeURIComponent(TARGET_PATH);
        go(redirectUrl);
    }

    // 4 秒后还没跳转成功（很可能是 storage 被禁用 / 极端 CSP 拦截），提示手动跳转
    setTimeout(function() {
        if (document.visibilityState === 'visible') {
            const fallback = document.getElementById('fallback');
            if (rawToken) {
                const sep = TARGET_PATH.includes('?') ? '&' : '?';
                showError('自动跳转失败，请手动点击', TARGET_PATH + sep + '_t=' + encodeURIComponent(rawToken));
            } else {
                const sep = SPA_SIGNIN_URL.includes('?') ? '&' : '?';
                showError('自动跳转失败，请手动点击', SPA_SIGNIN_URL + sep + 'redirect=' + encodeURIComponent(TARGET_PATH));
            }
        }
    }, TIMEOUT_MS);
})();
</script>
</body>
</html>