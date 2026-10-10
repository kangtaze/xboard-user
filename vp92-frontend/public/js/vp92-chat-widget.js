/**
 * VP92 AI 聊天组件
 * 右下角浮动按钮 + 毛玻璃聊天弹窗
 *
 * 视觉风格: 参考 adsa.html (毛玻璃 + 纯蓝渐变 + 状态点)
 * 增强功能: 复制/操作栏 / 悬浮球5秒无操作半隐 / XSS 防护
 *
 * 配置（可选，运行时每次请求都会重新读取）：
 *   window.VP92_CHAT_CONFIG = {
 *     apiUrl: '/api/v1/guest/ai/chat',  // 默认；如有 token 自动切到 user API
 *     authToken: '',                     // 用户 token（含 'Bearer ' 前缀，可选）
 *   }
 *
 * 落地页无需任何配置 —— widget 会自动从 localStorage('fe_auth_token') 推断 API。
 * Dashboard 可在登录后调用 window.VP92ChatWidget.refresh() 强制重读配置。
 */
(function() {
    'use strict';

    // 每次请求都从 window.VP92_CHAT_CONFIG + localStorage 重新读取，避免
    // 登录/登出后闭包常量失效（旧版 API_URL / AUTH_TOKEN 在脚本加载时就锁死了）
    function getActiveConfig() {
        const c = window.VP92_CHAT_CONFIG || {};
        // 落地页不传 config 时，从 localStorage 读 vp92-frontend 的登录态
        let token = c.authToken || '';
        if (!token) {
            try {
                token = localStorage.getItem('fe_auth_token') || '';
            } catch (e) { /* private mode 等异常，忽略 */
            }
        }
        return {
            apiUrl: c.apiUrl || (token ? '/api/v1/user/ai/chat' : '/api/v1/guest/ai/chat'),
            token: token,
        };
    }

    // XSS 防护: 过滤用户输入 + AI 回复中的 HTML 特殊字符
    // v76 新增: widget 之前用 textContent 已经防注入, 但保留 escapeHtml 用于 future inline-html 场景
    function escapeHtml(text) {
        if (text == null) return '';
        return String(text)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    const MAX_HISTORY = 10;
    const TELEGRAM_URL = 'https://t.me/vp92jisu';
    const DOCK_IDLE_MS = 5000;  // 悬浮球 5 秒无操作自动半隐

    // 状态
    let isOpen = false;
    let isTyping = false;
    let history = [];
    let dockTimer = null;

    // 注入样式
    const style = document.createElement('style');
    style.textContent = `
        #vp92-chat-widget { position: fixed; bottom: 24px; right: 24px; z-index: 9999; font-family: Inter, -apple-system, sans-serif; }
        #vp92-chat-btn {
            width: 60px; height: 60px; border-radius: 50%; border: none; cursor: pointer;
            background: linear-gradient(135deg, #0066ff, #0050cb); color: white;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 20px rgba(0, 102, 255, 0.45);
            transition: transform 0.3s cubic-bezier(0.34, 1.56, 0.64, 1), box-shadow 0.3s, opacity 0.4s;
            animation: vp92-pulse 2.5s infinite ease-in-out;
        }
        #vp92-chat-btn:hover { transform: scale(1.1) rotate(5deg); box-shadow: 0 6px 25px rgba(0, 102, 255, 0.6); }
        #vp92-chat-btn svg { width: 30px; height: 30px; }
        /* v76 新增: 悬浮球 5 秒无操作自动半隐 (adsa.html 同款) */
        #vp92-chat-btn.docked {
            transform: translateX(35px);
            opacity: 0.4;
            box-shadow: none;
            animation: none;
        }
        #vp92-chat-btn.docked:hover {
            transform: translateX(0) scale(1.1);
            opacity: 1;
        }
        @keyframes vp92-pulse {
            0%   { box-shadow: 0 0 0 0   rgba(0, 102, 255, 0.6); }
            70%  { box-shadow: 0 0 0 16px rgba(0, 102, 255, 0); }
            100% { box-shadow: 0 0 0 0   rgba(0, 102, 255, 0); }
        }
        #vp92-chat-box {
            position: absolute; bottom: 76px; right: 0; width: 360px; max-width: calc(100vw - 32px);
            height: 540px; max-height: calc(100vh - 120px);
            background: rgba(255, 255, 255, 0.75);                                          /* v76b 用户拍板: 用 adsa --glass-bg (0.85→0.75 更通透) */
            backdrop-filter: blur(16px);
            -webkit-backdrop-filter: blur(16px);
            border-radius: 20px;
            box-shadow: 0 8px 32px 0 rgba(31, 38, 135, 0.15);                               /* v76b 用户拍板: 用 adsa --shadow-sm 蓝调 (黑调→蓝调) */
            display: none; flex-direction: column;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.4);                                     /* 跟 adsa --glass-border 一致, 本来就 0.4, 不用改 */
            opacity: 0;
            transform: translateY(20px) scale(0.95);
            transition: opacity 0.3s cubic-bezier(0.16, 1, 0.3, 1), transform 0.3s cubic-bezier(0.16, 1, 0.3, 1);
        }
        #vp92-chat-box.open {
            display: flex;
            opacity: 1;
            transform: translateY(0) scale(1);
        }
        .vp92-chat-header {
            background: rgba(255, 255, 255, 0.3);                                     /* v76b 用户拍板: 用 adsa chat-header 浅透明,不要蓝渐变 */
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            color: #1e293b;                                                          /* adsa 深色字 */
            padding: 14px 18px; display: flex; align-items: center; gap: 10px; flex-shrink: 0;
        }
        .vp92-chat-header-logo { display: none; }                                     /* v76b 用户拍板: 删 VP92 logo 块, adsa 没 logo */
        .vp92-chat-header-title {
            font-size: 15px; font-weight: 600; flex: 1;
            display: flex; align-items: center; gap: 8px;
            color: #1e293b;                                                          /* adsa 深色字 */
        }
        /* v76 新增: 状态点 (adsa.html 同款绿点) */
        .vp92-chat-status {
            width: 8px; height: 8px; background: #10b981;
            border-radius: 50%;
            box-shadow: 0 0 0 2px rgba(255, 255, 255, 0.6);                         /* 浅 header 上换浅边, 不要白色重边 */
            animation: vp92-blink 2s infinite;
        }
        @keyframes vp92-blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0.5; }
        }
        .vp92-chat-close {
            background: none; border: none; color: #64748b; cursor: pointer;        /* v76b 用户拍板: 灰 ✕ (adsa 一致), 不是白色 */
            width: 28px; height: 28px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 18px;
            transition: background 0.15s;
        }
        .vp92-chat-close:hover { background: rgba(0, 0, 0, 0.05); }                  /* 浅 header 上换淡黑 hover */
        .vp92-chat-messages {
            flex: 1; overflow-y: auto; padding: 14px 14px 8px; display: flex;
            flex-direction: column; gap: 12px;
        }
        .vp92-msg {
            max-width: 85%; padding: 10px 14px; border-radius: 16px;
            font-size: 14px; line-height: 1.55; word-break: break-word;
            position: relative;  /* 容纳操作栏绝对定位 */
        }
        .vp92-msg-bot {
            align-self: flex-start; background: rgba(255, 255, 255, 0.85); color: #334155;  /* v76b 用户拍板: 用 adsa 0.85 + #334155, 不要 0.95 太白 */
            border-bottom-left-radius: 4px;
            box-shadow: 0 2px 6px rgba(0, 0, 0, 0.03);
        }
        .vp92-msg-user {
            align-self: flex-end;
            background: linear-gradient(135deg, #0066ff, #0050cb); color: white;
            border-bottom-right-radius: 4px;
        }
        .vp92-msg-typing {
            align-self: flex-start; background: rgba(255, 255, 255, 0.95); color: #666;
            font-style: italic;
        }
        /* v76 新增: 打字机光标 (▋ 备用, 后端流式再做) */
        .vp92-msg-typing::after {
            content: '▋';
            display: inline-block;
            margin-left: 2px;
            color: #0066ff;
            animation: vp92-cursor-blink 0.8s infinite;
        }
        @keyframes vp92-cursor-blink {
            0%, 100% { opacity: 1; }
            50%      { opacity: 0; }
        }
        /* v76 新增: AI 消息操作栏 (复制/👍/👎) hover 显示 */
        .vp92-msg-actions {
            display: flex; gap: 10px; margin-top: 4px;
            padding-left: 4px; font-size: 12px; color: #64748b;
            opacity: 0; transition: opacity 0.2s;
        }
        .vp92-msg-bot:hover .vp92-msg-actions { opacity: 1; }
        .vp92-msg-action {
            cursor: pointer; user-select: none; padding: 2px 4px;
            border-radius: 4px; transition: background 0.15s, color 0.15s;
        }
        .vp92-msg-action:hover { background: rgba(0, 102, 255, 0.08); color: #0066ff; }
        .vp92-msg-action.copied { color: #10b981; }
        .vp92-chat-suggestions {
            padding: 0 16px 8px; display: flex; flex-wrap: nowrap; gap: 8px; flex-shrink: 0;
            overflow-x: auto;                                                          /* v76b 用户拍板: 用 adsa quick-prompts 单行滚动, 不要 wrap 换行 */
            scrollbar-width: thin;
        }
        .vp92-suggestion-btn {
            font-size: 12px; padding: 6px 12px; border-radius: 12px;                 /* v76b 用户拍板: 用 adsa prompt-chip 灰底 + emoji, 不要蓝边框 */
            background: rgba(255, 255, 255, 0.6); border: 1px solid rgba(255, 255, 255, 0.5);
            color: #475569; white-space: nowrap; cursor: pointer;
            transition: background 0.2s;
            backdrop-filter: blur(4px); -webkit-backdrop-filter: blur(4px);
            flex-shrink: 0;
        }
        .vp92-suggestion-btn:hover { background: rgba(255, 255, 255, 0.9); }
        .vp92-chat-input {
            padding: 12px 16px; border-top: 1px solid rgba(255, 255, 255, 0.2); display: flex;
            gap: 8px; flex-shrink: 0;
            background: rgba(255, 255, 255, 0.4);                                    /* adsa chat-footer 浅玻璃 */
        }
        .vp92-chat-input input {
            flex: 1; border: 1px solid rgba(255, 255, 255, 0.6); border-radius: 12px;  /* v76b 用户拍板: 矩形 12px (adsa chat-input), 不要 20px 胶囊 */
            padding: 8px 12px;
            font-size: 14px; outline: none; transition: border-color 0.2s;
            color: #1a1a2e;
            background: rgba(255, 255, 255, 0.7);                                     /* adsa chat-input 浅玻璃底 */
        }
        .vp92-chat-input input::placeholder { color: #9aa0a6; }
        .vp92-chat-input input:focus { border-color: #0066ff; }
        .vp92-chat-input button {
            border: none; background: linear-gradient(135deg, #0066ff, #0050cb); color: white;
            border-radius: 12px; padding: 0 14px;                                     /* v76b 用户拍板: 矩形文字按钮 (adsa 发送), 不要 36x36 圆形 icon */
            cursor: pointer; font-weight: 500; min-width: 56px;
            transition: opacity 0.2s, transform 0.2s;
        }
        .vp92-chat-input button:hover:not(:disabled) { opacity: 0.9; }
        .vp92-chat-input button:disabled { background: #ccc; cursor: not-allowed; opacity: 0.6; }
        @media (max-width: 480px) {
            #vp92-chat-widget { bottom: 14px; right: 12px; }  /* 修复:手机端靠右下,但避开底部 CTA 按钮 */
            #vp92-chat-btn { width: 52px; height: 52px; box-shadow: 0 3px 12px rgba(0, 102, 255, 0.4); }  /* 修复:缩小按钮减少遮挡面积 */
            #vp92-chat-btn svg { width: 26px; height: 26px; }
            #vp92-chat-box { width: calc(100vw - 16px); height: calc(100vh - 100px); bottom: 80px; right: 0px; border-radius: 16px; }
        }
    `;
    document.head.appendChild(style);

    // 创建 DOM (v76 新增: header 加状态点 + 标题加 emoji)
    const widget = document.createElement('div');
    widget.id = 'vp92-chat-widget';
    widget.innerHTML = `
        <div id="vp92-chat-box">
            <div class="vp92-chat-header">
                <span class="vp92-chat-header-title">
                    <span class="vp92-chat-status"></span>
                    VP92 智能助手
                </span>
                <button class="vp92-chat-close" id="vp92-chat-close" title="关闭">✕</button>
            </div>
            <div class="vp92-chat-messages" id="vp92-chat-messages"></div>
            <div class="vp92-chat-suggestions" id="vp92-chat-suggestions"></div>
            <div class="vp92-chat-input">
                <input type="text" id="vp92-chat-input" placeholder="输入消息..." />
                <button id="vp92-chat-send" disabled>发送</button>
            </div>
        </div>
        <button id="vp92-chat-btn" title="AI 助手">
            <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                <path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>
                <circle cx="9" cy="10" r="1" fill="currentColor"/><circle cx="12" cy="10" r="1" fill="currentColor"/><circle cx="15" cy="10" r="1" fill="currentColor"/>
            </svg>
        </button>
    `;
    document.body.appendChild(widget);

    // 元素引用
    const chatBox = document.getElementById('vp92-chat-box');
    const chatBtn = document.getElementById('vp92-chat-btn');
    const chatClose = document.getElementById('vp92-chat-close');
    const messagesEl = document.getElementById('vp92-chat-messages');
    const suggestionsEl = document.getElementById('vp92-chat-suggestions');
    const inputEl = document.getElementById('vp92-chat-input');
    const sendBtn = document.getElementById('vp92-chat-send');

    // v76 新增: dockTimer — 悬浮球 5 秒无操作自动半隐 (adsa.html 同款)
    function resetDockTimer() {
        if (!chatBtn) return;
        chatBtn.classList.remove('docked');
        if (dockTimer) clearTimeout(dockTimer);
        dockTimer = setTimeout(() => {
            if (!isOpen) chatBtn.classList.add('docked');
        }, DOCK_IDLE_MS);
    }
    // 用户任何鼠标 / 触摸动作都重置 timer
    window.addEventListener('mousemove', resetDockTimer);
    window.addEventListener('touchstart', resetDockTimer);
    window.addEventListener('keydown', resetDockTimer);
    // 初始化 (脚本加载时已 idle, 启动 5 秒倒计时)
    resetDockTimer();

    // 切换聊天窗口
    chatBtn.addEventListener('click', () => {
        isOpen = !isOpen;
        chatBox.classList.toggle('open', isOpen);
        resetDockTimer();  // 用户主动操作, 重置 timer
        if (isOpen && messagesEl.children.length === 0) {
            addBotMessage('您好！我是 VP92 智能客服，请问有什么可以帮您的？', ['💡 常见问题', '💰 充值指引', '⚡ 如何使用']);
        }
        if (isOpen) inputEl.focus();
    });

    chatClose.addEventListener('click', () => {
        isOpen = false;
        chatBox.classList.remove('open');
        resetDockTimer();
    });

    // 输入事件
    inputEl.addEventListener('input', () => {
        sendBtn.disabled = !inputEl.value.trim() || isTyping;
    });

    inputEl.addEventListener('keydown', (e) => {
        if (e.key === 'Enter' && !sendBtn.disabled) send();
    });

    sendBtn.addEventListener('click', () => send());

    // 发送消息
    async function send(text) {
        // 兼容：DOM 事件处理器调用 send() 时会传入 Event 对象，需要忽略
        if (text && typeof text !== 'string') text = '';
        const message = text || inputEl.value.trim();
        if (!message || isTyping) return;

        inputEl.value = '';
        sendBtn.disabled = true;
        suggestionsEl.innerHTML = '';
        resetDockTimer();

        addUserMessage(message);
        isTyping = true;
        addTypingIndicator();

        try {
            // 动态读取最新 API + auth（解决闭包捕获 + 登录切换失效）
            const cfg = getActiveConfig();
            const headers = { 'Content-Type': 'application/json', 'Accept': 'application/json' };
            if (cfg.token) {
                // token 已含 "Bearer " 前缀（见 AuthService::generateAuthData）
                headers['Authorization'] = cfg.token;
            }
            const resp = await fetch(cfg.apiUrl, {
                method: 'POST',
                headers,
                body: JSON.stringify({ message, history })
            });
            const data = await resp.json();

            removeTypingIndicator();
            isTyping = false;

            if (data.data && data.data.reply) {
                history.push({ role: 'user', content: message });
                history.push({ role: 'assistant', content: data.data.reply });
                if (history.length > MAX_HISTORY * 2) {
                    history = history.slice(-MAX_HISTORY * 2);
                }
                addBotMessage(data.data.reply, data.data.suggestions || []);
            } else {
                addBotMessage('抱歉，服务暂时不可用，请稍后再试。\n💬 也可通过 Telegram 联系人工客服 →', ['联系人工客服 Telegram →']);
            }
        } catch (e) {
            removeTypingIndicator();
            isTyping = false;
            addBotMessage('网络错误，请检查连接后重试。\n💬 也可通过 Telegram 联系人工客服 →', ['联系人工客服 Telegram →']);
        }

        sendBtn.disabled = !inputEl.value.trim();
    }

    // 修复:用 scrollIntoView + rAF 替代直接 scrollTop,解决浏览器 layout 未完成时 scrollHeight 还是旧值导致不滚
    function scrollMessagesToBottom(smooth) {
        requestAnimationFrame(() => {
            const last = messagesEl.lastElementChild;
            if (last && typeof last.scrollIntoView === 'function') {
                last.scrollIntoView({ behavior: smooth ? 'smooth' : 'auto', block: 'end' });
            } else {
                messagesEl.scrollTop = messagesEl.scrollHeight;
            }
        });
    }

    // DOM 辅助
    function addBotMessage(text, suggestions) {
        const div = document.createElement('div');
        div.className = 'vp92-msg vp92-msg-bot';

        // v40 修复：把 [文字](URL) markdown 链接渲染成可点击 <a>
        // 修复前 widget 用 div.textContent = text → markdown 链接显示为纯文本，用户点不动
        // 安全：只解析 [text](url) 格式，不解析其他 HTML（避免 XSS）
        if (text && /\[[^\]]+\]\([^)]+\)/.test(text)) {
            const parts = text.split(/(\[[^\]]+\]\([^)]+\))/g);
            parts.forEach(part => {
                const linkMatch = part.match(/^\[([^\]]+)\]\(([^)]+)\)$/);
                if (linkMatch) {
                    const a = document.createElement('a');
                    a.href = linkMatch[2];
                    a.textContent = linkMatch[1];
                    a.target = '_blank';
                    a.rel = 'noopener noreferrer';
                    a.style.color = '#0066ff';
                    a.style.textDecoration = 'underline';
                    a.style.wordBreak = 'break-all';
                    div.appendChild(a);
                } else if (part) {
                    div.appendChild(document.createTextNode(part));
                }
            });
        } else {
            div.textContent = text;  // textContent 自动防 XSS
        }

        // 保留换行（widget 容器宽度 360px，长 URL 自动换行）
        div.style.whiteSpace = 'pre-wrap';

        // v76 新增: AI 消息操作栏 (复制 / 👍 / 👎) — adsa.html 同款
        const actions = document.createElement('div');
        actions.className = 'vp92-msg-actions';
        const copyBtn = document.createElement('span');
        copyBtn.className = 'vp92-msg-action';
        copyBtn.textContent = '复制';
        copyBtn.title = '复制回复内容';
        copyBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            copyToClipboard(text, copyBtn);
        });
        const upBtn = document.createElement('span');
        upBtn.className = 'vp92-msg-action';
        upBtn.textContent = '👍';
        upBtn.title = '有帮助';
        upBtn.addEventListener('click', (e) => { e.stopPropagation(); flashFeedback(upBtn, '✓'); });
        const downBtn = document.createElement('span');
        downBtn.className = 'vp92-msg-action';
        downBtn.textContent = '👎';
        downBtn.title = '没帮助';
        downBtn.addEventListener('click', (e) => { e.stopPropagation(); flashFeedback(downBtn, '✓'); });
        actions.appendChild(copyBtn);
        actions.appendChild(upBtn);
        actions.appendChild(downBtn);
        div.appendChild(actions);

        messagesEl.appendChild(div);
        scrollMessagesToBottom(true);  /* 修复:用 scrollIntoView 滚到底 */

        if (suggestions && suggestions.length > 0) {
            suggestionsEl.innerHTML = '';
            suggestions.forEach(s => {
                const btn = document.createElement('button');
                btn.className = 'vp92-suggestion-btn';
                btn.textContent = s;
                btn.addEventListener('click', () => {
                    if (s.includes('人工客服') || s.includes('Telegram')) {
                        window.open(TELEGRAM_URL, '_blank');
                    } else {
                        send(s);
                    }
                });
                suggestionsEl.appendChild(btn);
            });
        }
    }

    // v76 新增: 复制到剪贴板 + 1.5s 反馈 (adsa.html 同款)
    function copyToClipboard(text, btn) {
        // 优先用 navigator.clipboard (HTTPS / localhost), 失败 fallback textarea
        const fallback = () => {
            const ta = document.createElement('textarea');
            ta.value = text || '';
            ta.style.position = 'fixed';
            ta.style.opacity = '0';
            document.body.appendChild(ta);
            ta.select();
            try { document.execCommand('copy'); } catch (_) { /* 静默失败 */ }
            document.body.removeChild(ta);
            flashCopied(btn);
        };
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text || '').then(flashCopied.bind(null, btn)).catch(fallback);
        } else {
            fallback();
        }
    }
    function flashCopied(btn) {
        if (!btn) return;
        const orig = btn.textContent;
        btn.textContent = '已复制!';
        btn.classList.add('copied');
        setTimeout(() => {
            btn.textContent = orig;
            btn.classList.remove('copied');
        }, 1500);
    }
    function flashFeedback(btn, msg) {
        if (!btn) return;
        const orig = btn.textContent;
        btn.textContent = msg;
        setTimeout(() => { btn.textContent = orig; }, 1000);
    }

    function addUserMessage(text) {
        // v76 安全: 已用 textContent 防 XSS, escapeHtml 备用
        const div = document.createElement('div');
        div.className = 'vp92-msg vp92-msg-user';
        div.textContent = text;  // textContent 自动防 XSS
        messagesEl.appendChild(div);
        scrollMessagesToBottom(true);  /* 修复:同上 */
    }

    function addTypingIndicator() {
        const div = document.createElement('div');
        div.className = 'vp92-msg vp92-msg-typing';
        div.id = 'vp92-typing';
        div.textContent = '正在思考';
        messagesEl.appendChild(div);
        scrollMessagesToBottom(false);  /* 打字中即时滚(无 smooth 动画) */
    }

    function removeTypingIndicator() {
        const el = document.getElementById('vp92-typing');
        if (el) el.remove();
    }

    // 公开 API：外部（如登录成功后）调用可让 widget 下次发送立刻使用新配置
    // 实际上因为 send() 已改为每次动态读 config，这个方法主要用于强制重置内部状态
    // （如清空 history、清掉"网络错误"残留）。App.vue 的 token watcher 现在不需要调用它。
    window.VP92ChatWidget = {
        refresh: () => {
            history = [];
            const messages = document.getElementById('vp92-chat-messages');
            if (messages) messages.innerHTML = '';
            const suggestions = document.getElementById('vp92-chat-suggestions');
            if (suggestions) suggestions.innerHTML = '';
        },
        open: () => {
            if (!isOpen) chatBtn.click();
        },
        close: () => {
            if (isOpen) chatClose.click();
        },
    };
})();
