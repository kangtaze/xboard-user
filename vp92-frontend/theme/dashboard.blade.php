<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta http-equiv="Cache-Control" content="no-cache, no-store, must-revalidate">
  <meta http-equiv="Pragma" content="no-cache">
  <meta http-equiv="Expires" content="0">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>{{ $title ?? 'VP92极速加速器' }}</title>
  <link rel="preconnect" href="https://fonts.googleapis.com" crossorigin>
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" media="print" onload="this.media='all'">
  <noscript>
    <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap">
  </noscript>
  <link rel="modulepreload" crossorigin href="/theme/VP92/assets/vue-vendor-CohP-jTm.js">
  <link rel="stylesheet" crossorigin href="/theme/VP92/assets/index-CrfcEOxX.css">
  <style>body{background:#f7f9fb;color:#191c1e;-webkit-font-smoothing:antialiased;-moz-osx-font-smoothing:grayscale;margin:0;}.loading-indicator{position:fixed;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;font-family:system-ui,sans-serif;}.loading-indicator .bar{width:200px;height:3px;background:#e0e3e5;border-radius:2px;overflow:hidden;margin-top:16px;}.loading-indicator .bar-inner{width:40%;height:100%;background:#0066ff;border-radius:2px;animation:loading-slide 1.2s ease-in-out infinite;}@keyframes loading-slide{0%{transform:translateX(-100%)}100%{transform:translateX(350%)}}</style>
</head>
<body>
  <div id="app">
    <div class="loading-indicator" id="loading">
      <div style="font-size:14px;color:#0066ff;font-weight:700;letter-spacing:0.1em;">{{ $title ?? 'VP92极速加速器' }}</div>
      <div class="bar"><div class="bar-inner"></div></div>
    </div>
    <noscript><p style="padding:2rem;text-align:center;color:#0066ff;">请启用 JavaScript</p></noscript>
  </div>
  <script>
    window.settings = {
      title: {!! json_encode($title ?? 'VP92极速加速器') !!},
      theme: {!! json_encode($theme ?? 'VP92') !!},
      description: {!! json_encode($description ?? '') !!},
      logo: {!! json_encode($logo ?? '') !!},
      theme_config: {!! json_encode($theme_config ?? new stdClass()) !!}
    };
  </script>
  <script type="module" crossorigin src="/theme/VP92/assets/index-B4DW_UGC.js"></script>
</body>
</html>
