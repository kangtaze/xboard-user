/**
 * VP92 AI 聊天组件
 * 右下角浮动按钮 + 聊天弹窗
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
            } catch (e) { /* private mode 等异常，忽略 */ }
        }
        return {
            apiUrl: c.apiUrl || (token ? '/api/v1/user/ai/chat' : '/api/v1/guest/ai/chat'),
            token: token,
        };
    }

    const MAX_HISTORY = 10;
    const TELEGRAM_URL = 'https://t.me/vp92jisu';

    // 状态
    let isOpen = false;
    let isTyping = false;
    let history = [];

    // 注入样式
    const style = document.createElement('style');
    style.textContent = `
        #vp92-chat-widget { position: fixed; bottom: 24px; right: 24px; z-index: 9999; font-family: Inter, -apple-system, sans-serif; }
        #vp92-chat-btn {
            width: 56px; height: 56px; border-radius: 50%; border: none; cursor: pointer;
            background: linear-gradient(135deg, #0066ff, #6600ff); color: white;
            display: flex; align-items: center; justify-content: center;
            box-shadow: 0 4px 15px rgba(0,102,255,0.4);
            transition: transform 0.2s, box-shadow 0.2s;
        }
        #vp92-chat-btn:hover { transform: scale(1.1); box-shadow: 0 6px 20px rgba(0,102,255,0.5); }
        #vp92-chat-btn svg { width: 28px; height: 28px; }
        #vp92-chat-box {
            position: absolute; bottom: 70px; right: 0; width: 360px; max-width: calc(100vw - 32px);
            height: 480px; max-height: calc(100vh - 120px);
            background: #ffffff; border-radius: 16px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.15); display: none; flex-direction: column;
            overflow: hidden; border: 1px solid #e0e3e5;
        }
        #vp92-chat-box.open { display: flex; }
        .vp92-chat-header {
            background: linear-gradient(135deg, #0066ff, #6600ff); color: white;
            padding: 14px 16px; display: flex; align-items: center; gap: 10px; flex-shrink: 0;
        }
        .vp92-chat-header-logo {
            width: 32px; height: 32px; border-radius: 8px;
            background: rgba(255,255,255,0.2); display: flex; align-items: center; justify-content: center;
            font-weight: 800; font-size: 11px;
        }
        .vp92-chat-header-title { font-size: 15px; font-weight: 600; flex: 1; }
        .vp92-chat-close {
            background: none; border: none; color: white; cursor: pointer;
            width: 28px; height: 28px; border-radius: 50%; display: flex;
            align-items: center; justify-content: center; font-size: 18px;
        }
        .vp92-chat-close:hover { background: rgba(255,255,255,0.2); }
        .vp92-chat-messages {
            flex: 1; overflow-y: auto; padding: 12px; display: flex;
            flex-direction: column; gap: 10px;
        }
        .vp92-msg {
            max-width: 85%; padding: 10px 14px; border-radius: 16px;
            font-size: 14px; line-height: 1.5; word-break: break-word;
        }
        .vp92-msg-bot { align-self: flex-start; background: #f0f2f5; color: #1a1a2e; border-bottom-left-radius: 4px; }
        .vp92-msg-user { align-self: flex-end; background: #0066ff; color: white; border-bottom-right-radius: 4px; }
        .vp92-msg-typing { align-self: flex-start; background: #f0f2f5; color: #666; font-style: italic; }
        .vp92-chat-suggestions {
            padding: 4px 12px 8px; display: flex; flex-wrap: wrap; gap: 6px; flex-shrink: 0;
        }
        .vp92-suggestion-btn {
            padding: 6px 12px; border-radius: 16px; border: 1px solid #0066ff;
            background: none; color: #0066ff; font-size: 12px; cursor: pointer;
            transition: all 0.2s;
        }
        .vp92-suggestion-btn:hover { background: #0066ff; color: white; }
        .vp92-chat-input {
            padding: 10px 12px; border-top: 1px solid #e0e3e5; display: flex;
            gap: 8px; flex-shrink: 0;
        }
        .vp92-chat-input input {
            flex: 1; border: 1px solid #e0e3e5; border-radius: 20px; padding: 8px 14px;
            font-size: 14px; outline: none; transition: border-color 0.2s;
            color: #1a1a2e;  /* 修复:输入框字体清晰深色,避免 mobile 浏览器偏淡看不清 */
            background: #ffffff;  /* 显式白底,避免暗色主题继承 */
        }
        .vp92-chat-input input::placeholder { color: #9aa0a6; }  /* placeholder 单独控制 */
        .vp92-chat-input input:focus { border-color: #0066ff; }
        .vp92-chat-input button {
            width: 36px; height: 36px; border-radius: 50%; border: none;
            background: #0066ff; color: white; cursor: pointer;
            display: flex; align-items: center; justify-content: center;
            transition: background 0.2s;
        }
        .vp92-chat-input button:hover { background: #0050cb; }
        .vp92-chat-input button:disabled { background: #ccc; cursor: not-allowed; }
        .vp92-chat-input button svg { width: 18px; height: 18px; }
        @media (max-width: 480px) {
            #vp92-chat-widget { bottom: 14px; right: 12px; }  /* 修复:手机端靠右下,但避开底部 CTA 按钮 */
            #vp92-chat-btn { width: 48px; height: 48px; box-shadow: 0 3px 10px rgba(0,102,255,0.35); }  /* 修复:缩小按钮减少遮挡面积 */
            #vp92-chat-btn svg { width: 24px; height: 24px; }
            #vp92-chat-box { width: calc(100vw - 16px); height: calc(100vh - 100px); bottom: 72px; right: 0px; }
        }
    `;
    document.head.appendChild(style);

    // 创建 DOM
    const widget = document.createElement('div');
    widget.id = 'vp92-chat-widget';
    widget.innerHTML = `
        <div id="vp92-chat-box">
            <div class="vp92-chat-header">
                <div class="vp92-chat-header-logo">VP</div>
                <span class="vp92-chat-header-title">VP92智能客服</span>
                <button class="vp92-chat-close" id="vp92-chat-close">✕</button>
            </div>
            <div class="vp92-chat-messages" id="vp92-chat-messages"></div>
            <div class="vp92-chat-suggestions" id="vp92-chat-suggestions"></div>
            <div class="vp92-chat-input">
                <input type="text" id="vp92-chat-input" placeholder="输入您的问题..." />
                <button id="vp92-chat-send" disabled>
                    <svg viewBox="0 0 24 24" fill="currentColor"><path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/></svg>
                </button>
            </div>
        </div>
        <button id="vp92-chat-btn" title="AI客服">
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

    // 切换聊天窗口
    chatBtn.addEventListener('click', () => {
        isOpen = !isOpen;
        chatBox.classList.toggle('open', isOpen);
        if (isOpen && messagesEl.children.length === 0) {
            addBotMessage('您好！我是 VP92 智能客服，请问有什么可以帮您的？', ['如何连接节点？', '流量怎么计算？', '套餐有哪些？']);
        }
        if (isOpen) inputEl.focus();
    });

    chatClose.addEventListener('click', () => {
        isOpen = false;
        chatBox.classList.remove('open');
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
            div.textContent = text;
        }

        // 保留换行（widget 容器宽度 360px，长 URL 自动换行）
        div.style.whiteSpace = 'pre-wrap';

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

    function addUserMessage(text) {
        const div = document.createElement('div');
        div.className = 'vp92-msg vp92-msg-user';
        div.textContent = text;
        messagesEl.appendChild(div);
        scrollMessagesToBottom(true);  /* 修复:同上 */
    }

    function addTypingIndicator() {
        const div = document.createElement('div');
        div.className = 'vp92-msg vp92-msg-typing';
        div.id = 'vp92-typing';
        div.textContent = '正在思考...';
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