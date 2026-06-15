<!DOCTYPE html>
<html lang="zh-CN" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Worker 弹夹管理 - {{ $title ?? 'Xboard' }}</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 50:'#eef2ff',100:'#e0e7ff',200:'#c7d2fe',300:'#a5b4fc',400:'#818cf8',500:'#6366f1',600:'#4f46e5',700:'#4338ca',800:'#3730a3',900:'#312e81' }
                    }
                }
            }
        }
    </script>
    <style>
        [x-cloak] { display: none !important; }
        body { background: #0f172a; color: #e2e8f0; }
        .fade-in { animation: fadeIn 0.2s ease-in; }
        @keyframes fadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
        .pulse { animation: pulse 2s infinite; }
        @keyframes pulse { 0%,100% { opacity: 1; } 50% { opacity: 0.5; } }
    </style>
</head>
<body class="min-h-screen font-sans antialiased" x-data="cfWorkerApp()" x-cloak>

<!-- Header -->
<header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur border-b border-slate-700/50">
    <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/{{ $secure_path ?? 'admin' }}" class="text-slate-400 hover:text-white transition">&larr; 返回后台</a>
            <span class="text-slate-600">|</span>
            <h1 class="text-lg font-semibold text-white">Worker 弹夹管理</h1>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-400" x-text="'共 ' + workers.length + ' 个 Worker'"></span>
            <button @click="showAddModal = true"
                class="px-4 py-1.5 bg-brand-600 hover:bg-brand-500 text-white text-sm rounded-lg transition">
                + 添加 CF 账号
            </button>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8">

    <!-- 使用说明 -->
    <div class="mb-8 bg-slate-800/50 border border-slate-700/50 rounded-xl p-6">
        <h2 class="text-lg font-semibold text-brand-400 mb-4">使用说明</h2>
        <div class="grid md:grid-cols-2 gap-6">
            <div>
                <h3 class="text-sm font-medium text-slate-300 mb-2">添加步骤</h3>
                <ol class="text-sm text-slate-400 space-y-1.5 list-decimal list-inside">
                    <li>注册一个新的 Cloudflare 账号</li>
                    <li>进入 CF 仪表板，创建 API Token（需要 Workers/KV/Zone 权限）</li>
                    <li>将域名添加到该 CF 账号（或使用已有域名）</li>
                    <li>记录 Account ID 和 Zone ID（仪表板首页右侧可见）</li>
                    <li>在下方填入信息，点击「部署」即可</li>
                </ol>
            </div>
            <div>
                <h3 class="text-sm font-medium text-slate-300 mb-2">弹夹机制</h3>
                <ul class="text-sm text-slate-400 space-y-1.5 list-disc list-inside">
                    <li>所有 Worker 节点自动出现在用户订阅中</li>
                    <li>客户端自动选择延迟最低的节点（url-test）</li>
                    <li>当某个 CF 账号被限时，自动切换到下一个</li>
                    <li>无需手动干预，像弹夹一样自动切换</li>
                </ul>
                <h3 class="text-sm font-medium text-slate-300 mt-4 mb-2">API Token 权限要求</h3>
                <ul class="text-sm text-slate-400 space-y-1 list-disc list-inside">
                    <li>Account > Workers Scripts > Edit</li>
                    <li>Account > Workers KV Storage > Edit</li>
                    <li>Zone > Workers Routes > Edit</li>
                    <li>Zone > DNS > Edit（可选，用于自动添加 DNS）</li>
                </ul>
            </div>
        </div>
    </div>

    <!-- Worker 列表 -->
    <div class="space-y-4">
        <template x-for="worker in workers" :key="worker.id">
            <div class="bg-slate-800/50 border border-slate-700/50 rounded-xl p-5 fade-in">
                <div class="flex items-center justify-between">
                    <div class="flex items-center gap-4">
                        <!-- 状态灯 -->
                        <div class="w-3 h-3 rounded-full"
                             :class="worker.status === 1 ? 'bg-green-500' : worker.status === 2 ? 'bg-red-500' : 'bg-yellow-500'"></div>
                        <div>
                            <h3 class="font-medium text-white" x-text="worker.name"></h3>
                            <p class="text-sm text-slate-400" x-text="worker.domain"></p>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs px-2 py-0.5 rounded-full"
                              :class="worker.status === 1 ? 'bg-green-500/20 text-green-400' : worker.status === 2 ? 'bg-red-500/20 text-red-400' : 'bg-yellow-500/20 text-yellow-400'"
                              x-text="worker.status === 1 ? '已部署' : worker.status === 2 ? '错误' : '未部署'"></span>
                        <template x-if="worker.deployed_at">
                            <span class="text-xs text-slate-500" x-text="worker.deployed_at"></span>
                        </template>
                    </div>
                </div>

                <!-- 错误信息 -->
                <template x-if="worker.status === 2 && worker.error_msg">
                    <div class="mt-3 p-3 bg-red-500/10 border border-red-500/20 rounded-lg text-sm text-red-400" x-text="worker.error_msg"></div>
                </template>

                <!-- 操作按钮 -->
                <div class="mt-4 flex items-center gap-2">
                    <template x-if="worker.status === 0">
                        <button @click="deploy(worker.id)" :disabled="deploying === worker.id"
                                class="px-3 py-1.5 bg-brand-600 hover:bg-brand-500 disabled:opacity-50 text-white text-sm rounded-lg transition">
                            <span x-show="deploying !== worker.id">部署</span>
                            <span x-show="deploying === worker.id" class="pulse">部署中...</span>
                        </button>
                    </template>
                    <template x-if="worker.status === 1">
                        <button @click="redeploy(worker.id)" :disabled="deploying === worker.id"
                                class="px-3 py-1.5 bg-slate-600 hover:bg-slate-500 disabled:opacity-50 text-white text-sm rounded-lg transition">
                            <span x-show="deploying !== worker.id">重新部署</span>
                            <span x-show="deploying === worker.id" class="pulse">部署中...</span>
                        </button>
                        <button @click="syncUsers(worker.id)" :disabled="syncing === worker.id"
                                class="px-3 py-1.5 bg-green-600 hover:bg-green-500 disabled:opacity-50 text-white text-sm rounded-lg transition">
                            <span x-show="syncing !== worker.id">同步用户</span>
                            <span x-show="syncing === worker.id" class="pulse">同步中...</span>
                        </button>
                    </template>
                    <template x-if="worker.status === 2">
                        <button @click="deploy(worker.id)" :disabled="deploying === worker.id"
                                class="px-3 py-1.5 bg-yellow-600 hover:bg-yellow-500 disabled:opacity-50 text-white text-sm rounded-lg transition">
                            <span x-show="deploying !== worker.id">重试部署</span>
                            <span x-show="deploying === worker.id" class="pulse">部署中...</span>
                        </button>
                    </template>
                    <button @click="editWorker(worker)"
                            class="px-3 py-1.5 bg-slate-700 hover:bg-slate-600 text-white text-sm rounded-lg transition">
                        编辑
                    </button>
                    <button @click="deleteWorker(worker.id)"
                            class="px-3 py-1.5 bg-red-600/20 hover:bg-red-600/40 text-red-400 text-sm rounded-lg transition">
                        删除
                    </button>
                </div>
            </div>
        </template>

        <!-- 空状态 -->
        <template x-if="workers.length === 0 && !loading">
            <div class="text-center py-16 text-slate-500">
                <svg class="mx-auto w-16 h-16 mb-4 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M5 12h14M12 5l7 7-7 7"/>
                </svg>
                <p class="text-lg">还没有添加 CF 账号</p>
                <p class="text-sm mt-1">点击右上角「添加 CF 账号」开始</p>
            </div>
        </template>
    </div>
</main>

<!-- 添加/编辑 Modal -->
<div x-show="showAddModal" x-transition class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60">
    <div class="bg-slate-800 border border-slate-700 rounded-2xl w-full max-w-lg p-6" @click.outside="showAddModal = false">
        <h2 class="text-lg font-semibold text-white mb-4" x-text="editingId ? '编辑 CF 账号' : '添加 CF 账号'"></h2>

        <div class="space-y-4">
            <div>
                <label class="block text-sm text-slate-400 mb-1">名称（自定义标识）</label>
                <input type="text" x-model="form.name" placeholder="例如：账号1、美国节点"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-brand-500">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">CF Account ID</label>
                <input type="text" x-model="form.cf_account_id" placeholder="Cloudflare 仪表板首页右侧"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-brand-500">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">CF API Token</label>
                <input type="password" x-model="form.cf_api_token" placeholder="创建自定义 Token（Workers/KV/Zone 权限）"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-brand-500">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">Zone ID</label>
                <input type="text" x-model="form.cf_zone_id" placeholder="CF Dashboard 右侧栏 Zone ID（32位十六进制）"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-brand-500">
                <p class="text-xs text-slate-500 mt-1">登录 CF Dashboard → 选择域名 → 右侧栏 Zone ID（不是域名！）</p>
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1">域名</label>
                <input type="text" x-model="form.domain" placeholder="例如：node1.example.com"
                       class="w-full px-3 py-2 bg-slate-900 border border-slate-700 rounded-lg text-white text-sm focus:outline-none focus:border-brand-500">
                <p class="text-xs text-slate-500 mt-1">Worker 将绑定到此域名，确保域名已添加到对应 CF 账号</p>
            </div>
        </div>

        <div class="mt-6 flex justify-end gap-3">
            <button @click="showAddModal = false; resetForm()"
                    class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">取消</button>
            <button @click="saveWorker()" :disabled="saving"
                    class="px-4 py-2 bg-brand-600 hover:bg-brand-500 disabled:opacity-50 text-white text-sm rounded-lg transition">
                <span x-show="!saving" x-text="editingId ? '保存' : '添加'"></span>
                <span x-show="saving">保存中...</span>
            </button>
        </div>
    </div>
</div>

<!-- Toast -->
<div x-show="toast.show" x-transition class="fixed bottom-6 right-6 z-50">
    <div class="px-4 py-3 rounded-lg shadow-lg text-sm"
         :class="toast.type === 'success' ? 'bg-green-600 text-white' : 'bg-red-600 text-white'"
         x-text="toast.message"></div>
</div>

<script>
function getAuthToken() {
    try {
        const stored = localStorage.getItem('XBOARD_ACCESS_TOKEN');
        if (stored) {
            const parsed = JSON.parse(stored);
            if (parsed && parsed.value) return parsed.value;
        }
    } catch(e) {}
    return '';
}

function cfWorkerApp() {
    const SECURE_PATH = '{{ $secure_path ?? "admin" }}';
    const basePath = '/api/v2/' + SECURE_PATH;
    const authHeader = { 'Content-Type': 'application/json', 'Authorization': getAuthToken() };
    return {
        workers: [],
        loading: true,
        showAddModal: false,
        editingId: null,
        deploying: null,
        syncing: null,
        saving: false,
        form: { name: '', cf_account_id: '', cf_api_token: '', cf_zone_id: '', domain: '' },
        toast: { show: false, message: '', type: 'success' },

        async init() {
            await this.fetchWorkers();
        },

        async fetchWorkers() {
            this.loading = true;
            try {
                const res = await fetch(`${basePath}/cf-worker/list`, { headers: authHeader });
                const json = await res.json();
                if (json.data) this.workers = json.data;
            } catch (e) {
                this.showToast('加载失败: ' + e.message, 'error');
            }
            this.loading = false;
        },

        async saveWorker() {
            this.saving = true;
            try {
                const body = { ...this.form };
                if (this.editingId) body.id = this.editingId;
                const res = await fetch(`${basePath}/cf-worker/save`, {
                    method: 'POST',
                    headers: authHeader,
                    body: JSON.stringify(body)
                });
                const json = await res.json();
                if (json.data) {
                    this.showToast(this.editingId ? '保存成功' : '添加成功', 'success');
                    this.showAddModal = false;
                    this.resetForm();
                    await this.fetchWorkers();
                } else {
                    this.showToast(json.message || '保存失败', 'error');
                }
            } catch (e) {
                this.showToast('保存失败: ' + e.message, 'error');
            }
            this.saving = false;
        },

        async deploy(id) {
            this.deploying = id;
            try {
                const res = await fetch(`${basePath}/cf-worker/deploy`, {
                    method: 'POST',
                    headers: authHeader,
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.data) {
                    this.showToast('部署成功！节点已自动创建', 'success');
                } else {
                    this.showToast(json.message || '部署失败', 'error');
                }
                await this.fetchWorkers();
            } catch (e) {
                this.showToast('部署失败: ' + e.message, 'error');
            }
            this.deploying = null;
        },

        async redeploy(id) {
            this.deploying = id;
            try {
                const res = await fetch(`${basePath}/cf-worker/redeploy`, {
                    method: 'POST',
                    headers: authHeader,
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.data) {
                    this.showToast('重新部署成功', 'success');
                } else {
                    this.showToast(json.message || '重新部署失败', 'error');
                }
                await this.fetchWorkers();
            } catch (e) {
                this.showToast('重新部署失败: ' + e.message, 'error');
            }
            this.deploying = null;
        },

        async syncUsers(id) {
            this.syncing = id;
            try {
                const res = await fetch(`${basePath}/cf-worker/sync-users`, {
                    method: 'POST',
                    headers: authHeader,
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.data) {
                    this.showToast(json.message || '同步成功', 'success');
                } else {
                    this.showToast(json.message || '同步失败', 'error');
                }
            } catch (e) {
                this.showToast('同步失败: ' + e.message, 'error');
            }
            this.syncing = null;
        },

        async deleteWorker(id) {
            if (!confirm('确定删除此 Worker？已部署的将同时从 CF 移除')) return;
            try {
                const res = await fetch(`${basePath}/cf-worker/delete`, {
                    method: 'POST',
                    headers: authHeader,
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.data !== false) {
                    this.showToast('已删除', 'success');
                    await this.fetchWorkers();
                } else {
                    this.showToast(json.message || '删除失败', 'error');
                }
            } catch (e) {
                this.showToast('删除失败: ' + e.message, 'error');
            }
        },

        editWorker(worker) {
            this.editingId = worker.id;
            this.form = {
                name: worker.name,
                cf_account_id: worker.cf_account_id,
                cf_api_token: '', // 不回显 token
                cf_zone_id: worker.cf_zone_id,
                domain: worker.domain
            };
            this.showAddModal = true;
        },

        resetForm() {
            this.editingId = null;
            this.form = { name: '', cf_account_id: '', cf_api_token: '', cf_zone_id: '', domain: '' };
        },

        showToast(message, type = 'success') {
            this.toast = { show: true, message, type };
            setTimeout(() => { this.toast.show = false; }, 3000);
        }
    };
}
</script>
</body>
</html>
