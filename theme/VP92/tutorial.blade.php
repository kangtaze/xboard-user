<!DOCTYPE html>
<html lang="zh-CN">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>VP92极速加速器 - 使用教程</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@100..900&family=Material+Symbols+Outlined:wght,FILL@100..700,0..1&display=swap" rel="stylesheet">
  <style>
    * { margin: 0; padding: 0; box-sizing: border-box; }
    body { font-family: 'Inter', -apple-system, sans-serif; background: #f7f9fb; color: #191c1e; }
    .material-symbols-outlined { font-variation-settings: 'FILL' 0, 'wght' 400, 'GRAD' 0, 'opsz' 24; }

    /* === Header === */
    .tutorial-header {
      position: fixed; top: 0; left: 0; width: 100%; height: 64px; z-index: 100;
      background: #fff; border-bottom: 1px solid #e0e3e5;
      display: flex; align-items: center; justify-content: space-between; padding: 0 32px;
    }
    .header-left { display: flex; align-items: center; gap: 16px; }
    .header-logo { display: flex; align-items: center; gap: 10px; text-decoration: none; }
    .header-logo-icon {
      width: 36px; height: 36px; background: linear-gradient(135deg, #0066ff, #6600ff);
      border-radius: 10px; display: flex; align-items: center; justify-content: center;
    }
    .header-logo-icon span { color: #fff; font-weight: 800; font-size: 13px; }
    .header-logo-text { font-size: 17px; font-weight: 800; color: #191c1e; }
    .back-link {
      display: flex; align-items: center; gap: 6px; font-size: 14px; font-weight: 600;
      color: #424656; text-decoration: none; transition: color 0.2s;
    }
    .back-link:hover { color: #0066ff; }
    .cta-btn {
      padding: 10px 24px; background: linear-gradient(135deg, #0066ff, #0050cb); color: #fff;
      border: none; border-radius: 10px; font-size: 14px; font-weight: 700;
      cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; text-decoration: none;
    }
    .cta-btn:hover { transform: translateY(-1px); box-shadow: 0 4px 16px rgba(0,102,255,0.3); }

    /* === Main === */
    .tutorial-main { padding-top: 96px; padding-bottom: 80px; max-width: 960px; margin: 0 auto; padding-left: 24px; padding-right: 24px; }

    /* === Hero === */
    .tutorial-hero { text-align: center; margin-bottom: 48px; }
    .tutorial-hero h1 { font-size: 32px; font-weight: 900; color: #191c1e; margin-bottom: 8px; }
    .tutorial-hero p { font-size: 16px; color: #424656; }

    /* === Section === */
    .section-title {
      font-size: 20px; font-weight: 800; color: #191c1e; margin-bottom: 20px;
      display: flex; align-items: center; gap: 10px;
    }
    .section-title .material-symbols-outlined { color: #0066ff; }

    /* === Tab Bar === */
    .tab-bar {
      display: flex; gap: 4px; background: #fff; border-radius: 14px; padding: 4px;
      border: 1px solid #e0e3e5; margin-bottom: 24px; overflow-x: auto;
    }
    .tab-item {
      flex: 1; min-width: 0; padding: 12px 8px; border: none; background: none;
      border-radius: 10px; font-size: 13px; font-weight: 600; color: #424656;
      cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s;
      display: flex; align-items: center; justify-content: center; gap: 6px; white-space: nowrap;
    }
    .tab-item:hover { background: #f0f4ff; color: #0050cb; }
    .tab-item.active { background: #0066ff; color: #fff; box-shadow: 0 2px 8px rgba(0,102,255,0.25); }
    .tab-item svg { width: 18px; height: 18px; flex-shrink: 0; }

    /* === Tab Content === */
    .tab-panel { display: none; animation: fadeUp 0.3s ease; }
    .tab-panel.active { display: block; }
    @keyframes fadeUp { from { opacity: 0; transform: translateY(8px); } to { opacity: 1; transform: translateY(0); } }

    /* === Guide Card === */
    .guide-card {
      background: #fff; border-radius: 16px; padding: 32px; border: 1px solid #e0e3e5; margin-bottom: 24px;
    }
    .guide-client {
      display: flex; align-items: center; gap: 16px; margin-bottom: 24px;
      padding-bottom: 20px; border-bottom: 1px solid #f0f2f4;
    }
    .client-icon {
      width: 52px; height: 52px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .client-icon svg { width: 28px; height: 28px; }
    .client-icon.clash { background: #fee2e2; }
    .client-icon.singbox { background: #e0f2fe; }
    .client-icon.ss { background: #dcfce7; }
    .client-icon.trojan { background: #fef3c7; }
    .client-icon.v2ray { background: #f3e8ff; }
    .client-name { font-size: 18px; font-weight: 800; color: #191c1e; }
    .client-desc { font-size: 13px; color: #999; margin-top:  2px; }

    /* === Steps === */
    .step-list { display: flex; flex-direction: column; gap: 16px; margin-bottom: 24px; }
    .step-item {
      display: flex; gap: 16px; padding: 16px; background: #f7f9fb;
      border-radius: 12px; border: 1px solid #f0f2f4;
    }
    .step-num {
      width: 32px; height: 32px; border-radius: 10px; flex-shrink: 0;
      background: #0066ff; color: #fff; font-size: 14px; font-weight: 700;
      display: flex; align-items: center; justify-content: center;
    }
    .step-content { flex: 1; }
    .step-title { font-size: 14px; font-weight: 700; color: #191c1e; margin-bottom: 4px; }
    .step-desc { font-size: 13px; color: #424656; line-height: 1.6; }
    .step-desc code {
      background: #eef2ff; color: #0050cb; padding: 2px 8px; border-radius: 6px;
      font-size: 12px; font-weight: 600; word-break: break-all;
    }

    /* === Sub Copy Box === */
    .sub-copy-box {
      display: flex; align-items: center; gap: 12px; padding: 14px 16px;
      background: #f0f4ff; border-radius: 12px; margin-bottom: 16px;
    }
    .sub-copy-box .sub-label { font-size: 13px; font-weight: 600; color: #191c1e; white-space: nowrap; }
    .sub-copy-box .sub-url {
      flex: 1; font-size: 13px; color: #0050cb; font-weight: 600;
      overflow: hidden; text-overflow: ellipsis; white-space: nowrap;
    }
    .copy-btn {
      padding: 8px 20px; background: #0066ff; color: #fff; border: none; border-radius: 8px;
      font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif;
      transition: all 0.15s; white-space: nowrap;
    }
    .copy-btn:hover { background: #0050cb; }
    .copy-btn:active { transform: scale(0.96); }

    /* === Tips === */
    .tip-box {
      display: flex; gap: 12px; padding: 16px; background: #fefce8; border: 1px solid #fef08a;
      border-radius: 12px; margin-top: 16px;
    }
    .tip-box .material-symbols-outlined { color: #ca8a04; font-size: 20px; flex-shrink: 0; }
    .tip-box p { font-size: 13px; color: #854d0e; line-height: 1.6; }

    /* === Download Section === */
    .download-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 16px; }
    .dl-card {
      background: #fff; border-radius: 16px; padding: 24px; border: 1.5px solid #e0e3e5;
      display: flex; flex-direction: column; transition: all 0.25s; text-decoration: none; color: inherit;
    }
    .dl-card:hover { border-color: #0066ff; transform: translateY(-3px); box-shadow: 0 8px 24px rgba(0,102,255,0.12); }
    .dl-card-top { display: flex; align-items: center; gap: 14px; margin-bottom: 12px; }
    .dl-icon {
      width: 48px; height: 48px; border-radius: 14px;
      display: flex; align-items: center; justify-content: center; flex-shrink: 0;
    }
    .dl-icon svg { width: 26px; height: 26px; }
    .dl-icon.clash { background: #fee2e2; }
    .dl-icon.singbox { background: #e0f2fe; }
    .dl-icon.ss { background: #dcfce7; }
    .dl-icon.trojan { background: #fef3c7; }
    .dl-icon.v2ray { background: #f3e8ff; }
    .dl-name { font-size: 16px; font-weight: 800; color: #191c1e; }
    .dl-desc { font-size: 13px; color: #424656; line-height: 1.5; margin-bottom: 14px; flex: 1; }
    .dl-platforms { display: flex; gap: 6px; flex-wrap: wrap; margin-bottom: 14px; }
    .platform-tag {
      padding: 4px 10px; background: #f0f4ff; color: #0050cb;
      border-radius: 6px; font-size: 11px; font-weight: 600;
    }
    .dl-btn {
      display: flex; align-items: center; justify-content: center; gap: 8px;
      padding: 10px 0; background: #0066ff; color: #fff; border-radius: 10px;
      font-size: 14px; font-weight: 700; transition: all 0.2s;
    }
    .dl-card:hover .dl-btn { background: #0050cb; }
    .dl-btn .material-symbols-outlined { font-size: 18px; }

    /* === Toast === */
    .toast {
      position: fixed; top: 80px; right: 20px; z-index: 9999;
      padding: 14px 24px; border-radius: 12px; font-size: 14px; font-weight: 600;
      background: #191c1e; color: #fff; opacity: 0; transform: translateY(-10px);
      transition: all 0.3s; pointer-events: none;
    }
    .toast.show { opacity: 1; transform: translateY(0); }

    /* === Responsive === */
    @media (max-width: 768px) {
      .tutorial-header { padding: 0 16px; }
      .tutorial-main { padding-left: 16px; padding-right: 16px; }
      .tutorial-hero h1 { font-size: 24px; }
      .download-grid { grid-template-columns: 1fr; }
      .tab-bar { flex-wrap: nowrap; overflow-x: auto; -webkit-overflow-scrolling: touch; }
      .guide-card { padding: 20px; }
      .step-item { padding: 12px; }
      .back-link span { display: none; }
      .dl-card-top { flex-direction: column; align-items: flex-start; gap: 10px; }
    }
  </style>
</head>
<body>

<!-- Header -->
<header class="tutorial-header">
  <div class="header-left">
    <a href="/" class="header-logo">
      <div class="header-logo-icon"><span>VP</span></div>
      <span class="header-logo-text">VP92极速加速器</span>
    </a>
    <a href="/" class="back-link">
      <span class="material-symbols-outlined">arrow_back</span>
      <span>返回首页</span>
    </a>
  </div>
  <a href="/register" class="cta-btn">立即注册</a>
</header>

<!-- Main -->
<main class="tutorial-main">

  <!-- Hero -->
  <div class="tutorial-hero">
    <h1>使用教程</h1>
    <p>选择您的订阅格式，按照步骤快速配置，即可开始加速</p>
  </div>

  <!-- Section 1: 订阅配置教程 -->
  <h2 class="section-title">
    <span class="material-symbols-outlined">menu_book</span>
    订阅配置教程
  </h2>

  <div class="tab-bar" id="tabBar">
    <button class="tab-item active" data-tab="clash" onclick="switchTab('clash')">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3z"/></svg>
      Clash
    </button>
    <button class="tab-item" data-tab="singbox" onclick="switchTab('singbox')">
      <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 12h8M12 8v8"/></svg>
      SingBox
    </button>
    <button class="tab-item" data-tab="shadowsocks" onclick="switchTab('shadowsocks')">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/><path d="M2 7l10 5 10-5"/></svg>
      Shadowsocks
    </button>
    <button class="tab-item" data-tab="trojan" onclick="switchTab('trojan')">
      <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg>
      Trojan
    </button>
    <button class="tab-item" data-tab="v2ray" onclick="switchTab('v2ray')">
      <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12l3 3 5-6"/></svg>
      V2Ray
    </button>
  </div>

  <!-- Tab: Clash -->
  <div class="tab-panel active" id="panel-clash">
    <div class="guide-card">
      <div class="guide-client">
        <div class="client-icon clash">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3z"/></svg>
        </div>
        <div>
          <div class="client-name">Clash Verge Rev</div>
          <div class="client-desc">基于 Tauri 的现代化 Clash GUI 客户端，轻量高效</div>
        </div>
      </div>
      <div class="step-list">
        <div class="step-item">
          <div class="step-num">1</div>
          <div class="step-content">
            <div class="step-title">复制订阅链接</div>
            <div class="step-desc">在控制面板中找到 Clash 订阅链接，点击复制按钮</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div class="step-content">
            <div class="step-title">打开 Clash Verge Rev</div>
            <div class="step-desc">启动客户端，进入 <code>订阅 (Profiles)</code> 页面</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div class="step-content">
            <div class="step-title">导入订阅</div>
            <div class="step-desc">点击 <code>新建 (New)</code> → 选择 <code>URL</code> → 粘贴订阅链接 → 点击确认</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">4</div>
          <div class="step-content">
            <div class="step-title">更新并启用</div>
            <div class="step-desc">点击 <code>更新 (Update)</code> 拉取节点，然后在 <code>代理 (Proxies)</code> 页面选择节点，启用系统代理</div>
          </div>
        </div>
      </div>
      <div class="sub-copy-box">
        <span class="sub-label">Clash 订阅链接</span>
        <span class="sub-url">https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=clash</span>
        <button class="copy-btn" onclick="copyUrl('clash')">复制链接</button>
      </div>
      <div class="tip-box">
        <span class="material-symbols-outlined">info</span>
        <p>Clash Verge Rev 支持 Windows、macOS、Linux。首次使用需在设置中启用「系统代理」和「TUN 模式」以获得最佳体验。</p>
      </div>
    </div>
  </div>

  <!-- Tab: SingBox -->
  <div class="tab-panel" id="panel-singbox">
    <div class="guide-card">
      <div class="guide-client">
        <div class="client-icon singbox">
          <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 12h8M12 8v8"/></svg>
        </div>
        <div>
          <div class="client-name">sing-box</div>
          <div class="client-desc">通用代理平台，支持多种协议，高性能引擎</div>
        </div>
      </div>
      <div class="step-list">
        <div class="step-item">
          <div class="step-num">1</div>
          <div class="step-content">
            <div class="step-title">复制订阅链接</div>
            <div class="step-desc">在控制面板中找到 SingBox 订阅链接，点击复制</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div class="step-content">
            <div class="step-title">打开 sing-box 客户端</div>
            <div class="step-desc">启动客户端，进入订阅管理页面</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div class="step-content">
            <div class="step-title">添加订阅地址</div>
            <div class="step-desc">点击添加订阅，将复制的链接粘贴到 URL 输入框中，保存</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">4</div>
          <div class="step-content">
            <div class="step-title">更新节点并连接</div>
            <div class="step-desc">更新订阅获取最新节点列表，选择节点后启动代理连接</div>
          </div>
        </div>
      </div>
      <div class="sub-copy-box">
        <span class="sub-label">SingBox 订阅链接</span>
        <span class="sub-url">https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=singbox</span>
        <button class="copy-btn" onclick="copyUrl('singbox')">复制链接</button>
      </div>
      <div class="tip-box">
        <span class="material-symbols-outlined">info</span>
        <p>sing-box 支持 Windows、macOS、Linux、Android、iOS 全平台。移动端可使用 Hedgehog（iOS）或 Hiddify（Android）等 GUI 客户端。</p>
      </div>
    </div>
  </div>

  <!-- Tab: Shadowsocks -->
  <div class="tab-panel" id="panel-shadowsocks">
    <div class="guide-card">
      <div class="guide-client">
        <div class="client-icon ss">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/><path d="M2 7l10 5 10-5"/></svg>
        </div>
        <div>
          <div class="client-name">Shadowsocks</div>
          <div class="client-desc">经典开源加密代理，轻量稳定，多平台支持</div>
        </div>
      </div>
      <div class="step-list">
        <div class="step-item">
          <div class="step-num">1</div>
          <div class="step-content">
            <div class="step-title">复制订阅链接</div>
            <div class="step-desc">在控制面板中复制 Shadowsocks 订阅链接或节点 SS 链接</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div class="step-content">
            <div class="step-title">下载对应平台的客户端</div>
            <div class="step-desc">
              Windows / macOS / Linux: 使用 <code>sing-box</code> 或 <code>Clash Verge Rev</code><br/>
              Android: <code>sing-box</code>（Google Play / GitHub）<br/>
              iOS: <code>Shadowrocket</code>（App Store 付费）或 <code>Quantumult X</code>
            </div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div class="step-content">
            <div class="step-title">导入配置</div>
            <div class="step-desc">从剪贴板粘贴导入，或扫描二维码添加服务器节点</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">4</div>
          <div class="step-content">
            <div class="step-title">连接服务器</div>
            <div class="step-desc">选择服务器节点，启用系统代理模式，点击连接按钮</div>
          </div>
        </div>
      </div>
      <div class="sub-copy-box">
        <span class="sub-label">SS 订阅链接</span>
        <span class="sub-url">https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=shadowsocks</span>
        <button class="copy-btn" onclick="copyUrl('ss')">复制链接</button>
      </div>
      <div class="tip-box">
        <span class="material-symbols-outlined">info</span>
        <p>iOS 用户推荐使用 Shadowrocket（需在非国区 App Store 购买），也可使用 Quantumult X 或 Surge 等支持 SS 协议的客户端。</p>
      </div>
    </div>
  </div>

  <!-- Tab: Trojan -->
  <div class="tab-panel" id="panel-trojan">
    <div class="guide-card">
      <div class="guide-client">
        <div class="client-icon trojan">
          <svg viewBox="0 0 24 24" fill="none"><circle cx="12" cy="12" r="9"/><path d="M8 12h8M12 8v8"/></svg>
        </div>
        <div>
          <div class="client-name">Trojan</div>
          <div class="client-desc">TLS 伪装代理，流量伪装为正常 HTTPS，抗检测</div>
        </div>
      </div>
      <div class="step-list">
        <div class="step-item">
          <div class="step-num">1</div>
          <div class="step-content">
            <div class="step-title">复制订阅链接</div>
            <div class="step-desc">在控制面板中复制 Trojan 订阅链接</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div class="step-content">
            <div class="step-title">使用兼容客户端</div>
            <div class="step-desc">
              推荐使用 <code>Clash Verge Rev</code>、<code>V2RayN</code> 或 <code>Ignite</code> 等
              支持 Trojan 协议的客户端。也可使用 <code>sing-box</code> 内核客户端。
            </div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div class="step-content">
            <div class="step-title">导入 Trojan 节点</div>
            <div class="step-desc">在客户端中添加订阅 URL 或直接粘贴 Trojan 链接，格式为 <code>trojan://password@host:port</code></div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">4</div>
          <div class="step-content">
            <div class="step-title">启用连接</div>
            <div class="step-desc">选择 Trojan 节点，启用系统代理，开始使用</div>
          </div>
        </div>
      </div>
      <div class="sub-copy-box">
        <span class="sub-label">Trojan 订阅链接</span>
        <span class="sub-url">https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=trojan</span>
        <button class="copy-btn" onclick="copyUrl('trojan')">复制链接</button>
      </div>
      <div class="tip-box">
        <span class="material-symbols-outlined">info</span>
        <p>Trojan 协议利用 TLS 加密伪装流量，适合对抗深度包检测（DPI）。推荐搭配 Clash Verge Rev 使用以获得最佳体验。</p>
      </div>
    </div>
  </div>

  <!-- Tab: V2Ray -->
  <div class="tab-panel" id="panel-v2ray">
    <div class="guide-card">
      <div class="guide-client">
        <div class="client-icon v2ray">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12l3 3 5-6"/></svg>
        </div>
        <div>
          <div class="client-name">V2Ray</div>
          <div class="client-desc">多功能代理平台，支持 VMess/VLESS/SS/Trojan 等多种协议</div>
        </div>
      </div>
      <div class="step-list">
        <div class="step-item">
          <div class="step-num">1</div>
          <div class="step-content">
            <div class="step-title">复制订阅链接</div>
            <div class="step-desc">在控制面板中复制 V2Ray 订阅链接</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">2</div>
          <div class="step-content">
            <div class="step-title">打开 V2RayN</div>
            <div class="step-desc">启动 V2RayN 客户端，右键点击托盘图标打开主界面</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">3</div>
          <div class="step-content">
            <div class="step-title">添加订阅</div>
            <div class="step-desc">点击 <code>订阅</code> → <code>订阅设置</code> → <code>添加</code> → 粘贴订阅地址 → 确定</div>
          </div>
        </div>
        <div class="step-item">
          <div class="step-num">4</div>
          <div class="step-content">
            <div class="step-title">更新订阅并连接</div>
            <div class="step-desc">点击 <code>订阅</code> → <code>更新订阅</code>，然后在服务器列表中选择节点，启用系统代理</div>
          </div>
        </div>
      </div>
      <div class="sub-copy-box">
        <span class="sub-label">V2Ray 订阅链接</span>
        <span class="sub-url">https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=v2ray</span>
        <button class="copy-btn" onclick="copyUrl('v2ray')">复制链接</button>
      </div>
      <div class="tip-box">
        <span class="material-symbols-outlined">info</span>
        <p>V2RayN 支持 Windows、macOS、Linux。推荐使用最新版以获得 VLESS、Hysteria2 等新协议支持。Linux 用户也可使用 V2RayA 提供 Web GUI。</p>
      </div>
    </div>
  </div>

  <!-- Section 2: 应用下载 -->
  <h2 class="section-title" style="margin-top: 56px;">
    <span class="material-symbols-outlined">download</span>
    应用下载
  </h2>

  <div class="download-grid">

    <!-- Clash Verge Rev -->
    <a class="dl-card" href="https://github.com/clash-verge-rev/clash-verge-rev/releases" target="_blank" rel="noopener noreferrer">
      <div class="dl-card-top">
        <div class="dl-icon clash">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2zm0 3c1.66 0 3 1.34 3 3s-1.34 3-3 3-3-1.34-3-3 1.34-3 3-3z"/></svg>
        </div>
        <div class="dl-name">Clash Verge Rev</div>
      </div>
      <div class="dl-desc">基于 Tauri 的 Clash Meta 内核 GUI 客户端，轻量高效，支持 Windows、macOS、Linux</div>
      <div class="dl-platforms">
        <span class="platform-tag">Windows</span>
        <span class="platform-tag">macOS</span>
        <span class="platform-tag">Linux</span>
      </div>
      <div class="dl-btn">
        <span class="material-symbols-outlined">download</span>
        前往下载
      </div>
    </a>

    <!-- sing-box（内置 Trojan 支持） -->
    <a class="dl-card" href="https://github.com/SagerNet/sing-box/releases" target="_blank" rel="noopener noreferrer">
      <div class="dl-card-top">
        <div class="dl-icon singbox">
          <svg viewBox="0 0 24 24" fill="none"><rect x="3" y="3" width="18" height="18" rx="4"/><path d="M8 12h8M12 8v8"/></svg>
        </div>
        <div class="dl-name">sing-box</div>
      </div>
      <div class="dl-desc">通用代理平台，内置 Trojan 协议支持，同时兼容 SS/VMess/VLESS/Hysteria2 等，持续活跃更新</div>
      <div class="dl-platforms">
        <span class="platform-tag">Windows</span>
        <span class="platform-tag">macOS</span>
        <span class="platform-tag">Linux</span>
        <span class="platform-tag">Android</span>
        <span class="platform-tag">iOS</span>
      </div>
      <div class="dl-btn">
        <span class="material-symbols-outlined">download</span>
        前往下载
      </div>
    </a>

    <!-- Shadowsocks — 按平台分流 -->
    <a class="dl-card" href="https://github.com/shadowsocks/shadowsocks-rust/releases" target="_blank" rel="noopener noreferrer">
      <div class="dl-card-top">
        <div class="dl-icon ss">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2L2 7v10l10 5 10-5V7L12 2z"/><path d="M2 7l10 5 10-5"/></svg>
        </div>
        <div class="dl-name">Shadowsocks-rust</div>
      </div>
      <div class="dl-desc">Shadowsocks 官方 Rust 实现，高性能核心，持续维护更新。适用于 Windows、macOS、Linux 桌面端</div>
      <div class="dl-platforms">
        <span class="platform-tag">Windows</span>
        <span class="platform-tag">macOS</span>
        <span class="platform-tag">Linux</span>
      </div>
      <div class="dl-btn">
        <span class="material-symbols-outlined">download</span>
        前往下载
      </div>
    </a>

    <!-- iOS 客户端 -->
    <a class="dl-card" href="https://apps.apple.com/app/shadowrocket/id932747118" target="_blank" rel="noopener noreferrer">
      <div class="dl-card-top">
        <div class="dl-icon" style="background:#e0f2fe;">
          <svg viewBox="0 0 24 24" fill="none"><path d="M18.71 19.5C17.88 20.74 17 21.95 15.66 21.97C14.32 22 13.89 21.18 12.37 21.18C10.84 21.18 10.37 21.95 9.1 22C7.79 22.05 6.8 20.68 5.96 19.47C4.25 16.97 2.94 12.45 4.7 9.39C5.57 7.87 7.13 6.91 8.82 6.88C10.1 6.86 11.32 7.75 12.11 7.75C12.89 7.75 14.37 6.68 15.92 6.84C16.57 6.87 18.39 7.1 19.56 8.82C19.47 8.88 17.39 10.1 17.41 12.63C17.44 15.65 20.06 16.66 20.09 16.67C20.06 16.74 19.67 18.11 18.71 19.5ZM13 3.5C13.73 2.67 14.94 2.04 15.94 2C16.07 3.17 15.6 4.35 14.9 5.19C14.21 6.04 13.07 6.7 11.95 6.61C11.8 5.46 12.36 4.26 13 3.5Z"/></svg>
        </div>
        <div class="dl-name">Shadowrocket</div>
      </div>
      <div class="dl-desc">iOS 平台最受欢迎的代理客户端，支持 SS/VMess/Trojan/VLESS 等全协议（App Store 付费，需非国区账号）</div>
      <div class="dl-platforms">
        <span class="platform-tag">iOS</span>
        <span class="platform-tag">iPadOS</span>
      </div>
      <div class="dl-btn">
        <span class="material-symbols-outlined">open_in_new</span>
        App Store
      </div>
    </a>

    <!-- V2RayN -->
    <a class="dl-card" href="https://github.com/2dust/v2rayN/releases" target="_blank" rel="noopener noreferrer">
      <div class="dl-card-top">
        <div class="dl-icon v2ray">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M8 12l3 3 5-6"/></svg>
        </div>
        <div class="dl-name">V2RayN</div>
      </div>
      <div class="dl-desc">最受欢迎的 V2Ray 桌面 GUI 客户端，支持 Xray/sing-box 内核，功能全面</div>
      <div class="dl-platforms">
        <span class="platform-tag">Windows</span>
        <span class="platform-tag">macOS</span>
        <span class="platform-tag">Linux</span>
      </div>
      <div class="dl-btn">
        <span class="material-symbols-outlined">download</span>
        前往下载
      </div>
    </a>

    <!-- V2RayA -->
    <a class="dl-card" href="https://github.com/v2rayA/v2rayA" target="_blank" rel="noopener noreferrer">
      <div class="dl-card-top">
        <div class="dl-icon v2ray">
          <svg viewBox="0 0 24 24" fill="none"><path d="M12 2C6.48 2 2 6.48 2 12s4.48 10 10 10 10-4.48 10-10S17.52 2 12 2z"/><path d="M12 9v6M9 12h6"/></svg>
        </div>
        <div class="dl-name">V2RayA</div>
      </div>
      <div class="dl-desc">Web 图形化客户端，适合 Linux 服务器/NAS，支持全局透明代理和 Docker 部署</div>
      <div class="dl-platforms">
        <span class="platform-tag">Linux</span>
        <span class="platform-tag">Docker</span>
        <span class="platform-tag">OpenWrt</span>
      </div>
      <div class="dl-btn">
        <span class="material-symbols-outlined">download</span>
        前往下载
      </div>
    </a>

  </div>

</main>

<!-- Toast -->
<div class="toast" id="toast"></div>

<script>
// Tab switching
function switchTab(tab) {
  document.querySelectorAll('.tab-item').forEach(t => t.classList.remove('active'));
  document.querySelectorAll('.tab-panel').forEach(p => p.classList.remove('active'));
  document.querySelector('[data-tab="' + tab + '"]').classList.add('active');
  document.getElementById('panel-' + tab).classList.add('active');
}

// Copy URL
const subUrls = {
  clash: 'https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=clash',
  singbox: 'https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=singbox',
  ss: 'https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=shadowsocks',
  trojan: 'https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=trojan',
  v2ray: 'https://ferrari.cc.cd/api/v1/client/subscribe?token=xxxxx&flag=v2ray'
};

function copyUrl(type) {
  const url = subUrls[type] || subUrls.clash;
  if (navigator.clipboard) {
    navigator.clipboard.writeText(url).then(() => showToast('已复制订阅链接'));
  } else {
    const ta = document.createElement('textarea');
    ta.value = url; document.body.appendChild(ta);
    ta.select(); document.execCommand('copy');
    document.body.removeChild(ta);
    showToast('已复制订阅链接');
  }
}

// Toast
function showToast(msg) {
  let t = document.querySelector('.toast');
  if (!t) {
    t = document.createElement('div');
    t.className = 'toast';
    document.body.appendChild(t);
  }
  t.textContent = msg;
  t.classList.add('show');
  setTimeout(() => t.classList.remove('show'), 2500);
}
</script>

</body>
</html>