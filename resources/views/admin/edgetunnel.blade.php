<!DOCTYPE html>
<html lang="zh-CN" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edgetunnel 管理 - {{ $title ?? 'Xboard' }}</title>
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
    </style>
</head>
<body class="min-h-screen font-sans antialiased" x-data="edgetunnelApp()" x-cloak>

<!-- Header -->
<header class="sticky top-0 z-30 bg-slate-900/80 backdrop-blur border-b border-slate-700/50">
    <div class="max-w-6xl mx-auto px-6 h-14 flex items-center justify-between">
        <div class="flex items-center gap-3">
            <a href="/{{ $secure_path ?? 'admin' }}" class="text-slate-400 hover:text-white transition">&larr; 返回后台</a>
            <span class="text-slate-600">|</span>
            <h1 class="text-lg font-semibold text-white">Edgetunnel 节点管理</h1>
        </div>
        <div class="flex items-center gap-3">
            <span class="text-sm text-slate-400" x-text="'共 ' + subscriptions.length + ' 个订阅'"></span>
            <button @click="showAddModal = true"
                class="px-4 py-1.5 bg-brand-600 hover:bg-brand-500 text-white text-sm rounded-lg transition">
                + 添加订阅
            </button>
        </div>
    </div>
</header>

<main class="max-w-6xl mx-auto px-6 py-8">

    <!-- Server Records -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-brand-400" viewBox="0 0 20 20" fill="currentColor"><path d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-7 7a1 1 0 01-1.414 0l-7-7A1 1 0 011 6.586V4z"/><path d="M11 4a1 1 0 10-2 0v1a1 1 0 002 1h1a1 1 0 001-1V4z"/><path d="M5 4a1 1 0 10-2 0v2a3 3 0 006 0V4z"/></svg>
                Server 记录（Worker 流量统计）
            </h2>
            <button @click="showServerModal = true"
                class="px-3 py-1.5 text-xs rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 transition">
                + 添加 Server
            </button>
        </div>
        <div x-show="servers.length > 0" class="bg-slate-800/50 rounded-xl border border-slate-700/50 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                        <th class="px-5 py-2.5">ID (NODE_ID)</th>
                        <th class="px-3 py-2.5">名称</th>
                        <th class="px-3 py-2.5">Host</th>
                        <th class="px-3 py-2.5">套餐组</th>
                        <th class="px-5 py-2.5 text-right">操作</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    <template x-for="srv in servers" :key="srv.id">
                        <tr class="hover:bg-slate-700/20 transition">
                            <td class="px-5 py-2.5 font-mono text-brand-400 text-xs" x-text="'NODE_ID=' + srv.id"></td>
                            <td class="px-3 py-2.5 text-slate-200 text-xs" x-text="srv.name"></td>
                            <td class="px-3 py-2.5 text-slate-400 font-mono text-xs" x-text="srv.host + ':' + srv.port"></td>
                            <td class="px-3 py-2.5">
                                <template x-for="gid in srv.group_ids" :key="gid">
                                    <span class="inline-block text-xs px-1.5 py-0.5 rounded bg-slate-700/50 text-slate-400 mr-1" x-text="getGroupName(gid)"></span>
                                </template>
                            </td>
                            <td class="px-5 py-2.5 text-right">
                                <button @click="editServer(srv)" class="px-2 py-1 text-xs rounded bg-brand-500/10 text-brand-400 hover:bg-brand-500/20 transition mr-1">编辑</button>
                                <button @click="copyNodeId(srv.id)" class="px-2 py-1 text-xs rounded bg-slate-600/50 text-slate-400 hover:bg-slate-600 transition mr-1">复制 ID</button>
                                <button @click="confirmDropServer(srv)" class="px-2 py-1 text-xs rounded bg-red-500/10 text-red-400 hover:bg-red-500/20 transition">删除</button>
                            </td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div x-show="servers.length === 0 && !loading" class="text-center py-8 text-slate-500 text-sm">
            暂无 Server 记录，点击"添加 Server"创建
        </div>
    </div>

    <!-- Batch User Group Update -->
    <div class="mb-8">
        <div class="flex items-center justify-between mb-4">
            <h2 class="text-base font-semibold text-white flex items-center gap-2">
                <svg class="w-4 h-4 text-brand-400" viewBox="0 0 20 20" fill="currentColor"><path d="M9 6a3 3 0 11-6 0 3 3 0 016 0zM17 6a3 3 0 11-6 0 3 3 0 016 0zM4 13a1 1 0 011 1v1a1 1 0 01-2 0v-1a1 1 0 011-1zm-1 3a1 1 0 100 2h1a1 1 0 100-2H3zM15 11a1 1 0 100 2h1a1 1 0 100-2h-1z"/></svg>
                批量修改用户组
            </h2>
            <div class="flex items-center gap-2">
                <span class="text-xs text-slate-400" x-show="selectedUsers.length > 0" x-text="'已选 ' + selectedUsers.length + ' 人'"></span>
                <select x-model="batchTargetGroupId" class="bg-slate-900 border border-slate-600 rounded-lg px-2 py-1 text-xs text-white focus:outline-none focus:border-brand-500">
                    <template x-for="group in planGroups" :key="group.id">
                        <option :value="group.id" x-text="group.name"></option>
                    </template>
                </select>
                <button @click="batchUpdateGroup()" :disabled="selectedUsers.length === 0"
                    class="px-3 py-1 text-xs rounded-lg bg-brand-600 hover:bg-brand-500 text-white transition disabled:opacity-50">
                    执行
                </button>
            </div>
        </div>
        <div x-show="userList.length > 0" class="bg-slate-800/50 rounded-xl border border-slate-700/50 overflow-hidden">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                        <th class="px-4 py-2.5 w-8"><input type="checkbox" @change="toggleAllUsers($event.target.checked)" :checked="selectedUsers.length === userList.length"></th>
                        <th class="px-3 py-2.5">ID</th>
                        <th class="px-3 py-2.5">邮箱</th>
                        <th class="px-3 py-2.5">当前组</th>
                        <th class="px-3 py-2.5">状态</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    <template x-for="u in userList" :key="u.id">
                        <tr class="hover:bg-slate-700/20 transition" :class="selectedUsers.includes(u.id) && 'bg-brand-500/5'">
                            <td class="px-4 py-2"><input type="checkbox" :checked="selectedUsers.includes(u.id)" @change="toggleUser(u.id, $event.target.checked)"></td>
                            <td class="px-3 py-2 text-xs font-mono text-slate-400" x-text="u.id"></td>
                            <td class="px-3 py-2 text-xs text-slate-200" x-text="u.email"></td>
                            <td class="px-3 py-2">
                                <span class="text-xs px-1.5 py-0.5 rounded" :class="u.group_id ? 'bg-brand-500/10 text-brand-400' : 'bg-red-500/10 text-red-400'" x-text="getGroupName(u.group_id)"></span>
                            </td>
                            <td class="px-3 py-2 text-xs" :class="isUserActive(u) ? 'text-emerald-400' : 'text-red-400'" x-text="isUserActive(u) ? '正常' : '过期'"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
        </div>
        <div x-show="userList.length === 0 && !loadingUsers" class="text-center py-6 text-slate-500 text-sm">加载中...</div>
    </div>

    <!-- Empty State -->
    <div x-show="subscriptions.length === 0 && !loading" class="text-center py-20">
        <div class="text-6xl mb-4 opacity-30">&#9729;</div>
        <p class="text-slate-400 text-lg mb-2">暂无 Edgetunnel 订阅</p>
        <p class="text-slate-500 text-sm">点击上方按钮添加你的第一个订阅</p>
    </div>

    <!-- Loading -->
    <div x-show="loading" class="text-center py-20">
        <div class="inline-block w-8 h-8 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
        <p class="text-slate-400 mt-3">加载中...</p>
    </div>

    <!-- Subscription Cards -->
    <div class="space-y-4">
        <template x-for="sub in subscriptions" :key="sub.id">
            <div class="bg-slate-800/50 rounded-xl border border-slate-700/50 overflow-hidden">
                <!-- Card Header -->
                <div class="px-5 py-4 flex items-center justify-between">
                    <div class="flex items-center gap-3">
                        <button @click="toggleNodes(sub.id)" class="w-6 h-6 flex items-center justify-center text-slate-400 hover:text-white transition">
                            <svg class="w-4 h-4 transition-transform" :class="expandedSub === sub.id && 'rotate-90'" viewBox="0 0 20 20" fill="currentColor">
                                <path fill-rule="evenodd" d="M7.21 14.77a.75.75 0 01.02-1.06L11.168 10 7.23 6.29a.75.75 0 111.04-1.08l4.5 4.25a.75.75 0 010 1.08l-4.5 4.25a.75.75 0 01-1.06-.02z" clip-rule="evenodd"/>
                            </svg>
                        </button>
                        <div>
                            <div class="flex items-center gap-2">
                                <span class="font-medium text-white" x-text="sub.name"></span>
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :class="sub.enabled ? 'bg-emerald-500/10 text-emerald-400' : 'bg-slate-700 text-slate-400'"
                                    x-text="sub.enabled ? '启用' : '停用'"></span>
                                <span class="text-xs px-2 py-0.5 rounded-full"
                                    :class="sub.show ? 'bg-blue-500/10 text-blue-400' : 'bg-slate-700 text-slate-400'"
                                    x-text="sub.show ? '可见' : '隐藏'"></span>
                            </div>
                            <p class="text-xs text-slate-500 mt-0.5 truncate max-w-md" x-text="sub.url"></p>
                            <div class="flex items-center gap-1.5 mt-1" x-show="sub.group_ids && sub.group_ids.length > 0">
                                <template x-for="gid in sub.group_ids" :key="gid">
                                    <span class="text-xs px-1.5 py-0.5 rounded bg-slate-700/50 text-slate-400"
                                          x-text="gid === 'all' ? '全部套餐组' : getGroupName(gid)"></span>
                                </template>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5" x-show="sub.dns_cf_worker_id">
                                <svg class="w-3 h-3 text-blue-400" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M5.937 3.375A2.625 2.625 0 018.562.75h2.876c1.17 0 2.175.772 2.511 1.838l.39 1.262c.064.207.108.422.13.644l.29 3.084c.047.5.048 1.004.002 1.504l-.175 1.883a8.96 8.96 0 01-.726 2.59l-.069.142a2.625 2.625 0 01-2.326 1.407H9.093a2.625 2.625 0 01-2.326-1.407l-.069-.142a8.96 8.96 0 01-.726-2.59l-.175-1.883a8.96 8.96 0 01.002-1.504l.29-3.084a4.13 4.13 0 01.13-.644l.39-1.262z" clip-rule="evenodd"/></svg>
                                <span class="text-xs text-blue-300" x-text="'Worker: ' + (sub.dns_zone_name || 'ID:' + sub.dns_cf_worker_id)"></span>
                            </div>
                            <div class="flex items-center gap-2 mt-0.5" x-show="!sub.dns_cf_worker_id">
                                <svg class="w-3 h-3 text-yellow-500" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.485 2.495c.673-1.167 2.357-1.167 3.03 0l6.28 10.875c.673 1.167-.168 2.625-1.516 2.625H3.72c-1.347 0-2.189-1.458-1.515-2.625L8.485 2.495zM10 5a.75.75 0 01.75.75v3.5a.75.75 0 01-1.5 0v-3.5A.75.75 0 0110 5zm0 9a1 1 0 100-2 1 1 0 000 2z" clip-rule="evenodd"/></svg>
                                <span class="text-xs text-yellow-400">未关联 Worker</span>
                            </div>
                        </div>
                    </div>
                    <div class="flex items-center gap-2">
                        <span class="text-xs text-slate-400" x-text="sub.node_count + ' 个节点'"></span>
                        <span class="text-xs text-slate-500" x-show="sub.last_sync_at" x-text="'同步: ' + formatTime(sub.last_sync_at)"></span>
                        <button @click="syncSubscription(sub.id)" :disabled="syncingId !== null"
                            class="px-3 py-1 text-xs rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 transition disabled:opacity-50">
                            同步
                        </button>
                        <button @click="previewInjection(sub.id)" :disabled="previewingId !== null"
                            class="px-3 py-1 text-xs rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 transition disabled:opacity-50">
                            预览注入
                        </button>
                        <button @click="editSubscription(sub)" class="p-1 text-slate-400 hover:text-white transition">
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path d="M2.695 14.763l-1.262 3.154a.5.5 0 00.65.65l3.155-1.262a4 4 0 001.343-.885L17.5 5.5a2.121 2.121 0 00-3-3L3.58 13.42a4 4 0 00-.885 1.343z"/></svg>
                        </button>
                        <button @click="confirmDrop(sub)" class="p-1 text-slate-400 hover:text-red-400 transition">
                            <svg class="w-4 h-4" viewBox="0 0 20 20" fill="currentColor"><path fill-rule="evenodd" d="M8.75 1A2.75 2.75 0 006 3.75v.443c-.795.077-1.584.176-2.365.298a.75.75 0 10.23 1.482l.149-.022 1.896 8.698a.75.75 0 00.729.571H13.37a.75.75 0 00.729-.571l1.897-8.698.149.022a.75.75 0 10.23-1.482A41.03 41.03 0 0014 4.193V3.75A2.75 2.75 0 0011.25 1h-2.5zM10 4c.84 0 1.673.025 2.5.075V3.75c0-.69-.56-1.25-1.25-1.25h-2.5c-.69 0-1.25.56-1.25 1.25v.325C8.327 4.025 9.16 4 10 4zM8.58 7.72a.75.75 0 01.78.06l1.34 1.05a.75.75 0 11-.93 1.17l-.78-.61-.65 2.08a.75.75 0 01-1.44-.44l.68-2.2a.75.75 0 01.4-.5l.6.99zm2.84 0a.75.75 0 00-1.19.9l.6-.99a.75.75 0 01.4.5l.68 2.2a.75.75 0 01-1.44.44l-.65-2.08-.78.61a.75.75 0 01-.93-1.17l1.34-1.05a.75.75 0 01.78-.06l.59-.5z" clip-rule="evenodd"/></svg>
                        </button>
                    </div>
                </div>

                <!-- Node List (expandable) -->
                <div x-show="expandedSub === sub.id" x-transition class="border-t border-slate-700/30">
                    <!-- Node loading -->
                    <div x-show="loadingNodes[sub.id]" class="px-5 py-4 text-center">
                        <div class="inline-block w-6 h-6 border-2 border-brand-500 border-t-transparent rounded-full animate-spin"></div>
                    </div>
                    <!-- Nodes content -->
                    <div x-show="!loadingNodes[sub.id]">
                        <div x-show="!nodeGroups[sub.id] || nodeGroups[sub.id].length === 0" class="px-5 py-6 text-center text-slate-500 text-sm">
                            暂无节点，点击"同步"获取
                        </div>
                        <template x-for="group in nodeGroups[sub.id]" :key="group.id">
                            <div>
                            <table class="w-full text-sm">
                                <thead>
                                    <tr class="text-left text-xs text-slate-500 uppercase tracking-wider">
                                        <th class="px-5 py-2">名称</th>
                                        <th class="px-3 py-2">地址</th>
                                        <th class="px-3 py-2">类型</th>
                                        <th class="px-3 py-2 text-center">延迟</th>
                                        <th class="px-3 py-2 text-center">状态</th>
                                        <th class="px-5 py-2 text-right">操作</th>
                                    </tr>
                                </thead>
                                <tbody class="divide-y divide-slate-700/30">
                                    <template x-for="node in group.nodes" :key="node.id">
                                        <tr class="hover:bg-slate-700/20 transition">
                                            <td class="px-5 py-2.5">
                                                <span class="text-slate-200" x-text="node.name"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-slate-400 font-mono text-xs" x-text="node.host + ':' + node.port"></td>
                                            <td class="px-3 py-2.5">
                                                <span class="text-xs px-2 py-0.5 rounded-full bg-brand-500/10 text-brand-400" x-text="node.type.toUpperCase()"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-center">
                                                <span :class="node.latency > 0 ? (node.latency < 200 ? 'text-emerald-400' : node.latency < 500 ? 'text-yellow-400' : 'text-red-400') : 'text-slate-500'"
                                                    x-text="node.latency > 0 ? node.latency + 'ms' : '-'"></span>
                                            </td>
                                            <td class="px-3 py-2.5 text-center">
                                                <span class="inline-block w-2 h-2 rounded-full"
                                                    :class="node.online ? 'bg-emerald-400' : 'bg-slate-600'"></span>
                                            </td>
                                            <td class="px-5 py-2.5 text-right">
                                                <button @click="testSingleNode(node)" :disabled="testingNode"
                                                    class="px-2 py-1 text-xs rounded bg-slate-700 hover:bg-slate-600 text-slate-300 transition disabled:opacity-50">
                                                    测试
                                                </button>
                                            </td>
                                        </tr>
                                    </template>
                                </tbody>
                            </table>
                            <div class="px-5 py-3 flex items-center justify-between bg-slate-800/30">
                                <button @click="batchTestNodes(sub.id)" :disabled="testingNode"
                                    class="px-3 py-1.5 text-xs rounded-lg bg-slate-700 hover:bg-slate-600 text-slate-300 transition disabled:opacity-50">
                                    批量测试延迟
                                </button>
                                <span class="text-xs text-slate-500" x-text="group.nodes.length + ' 个节点'"></span>
                            </div>
                            </div>
                        </template>
                    </div>
                </div>
            </div>
        </template>
    </div>
</main>

<!-- Add/Edit Modal -->
<div x-show="showAddModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-transition>
    <div class="bg-slate-800 rounded-2xl border border-slate-700 w-full max-w-md mx-4 shadow-2xl" @click.away="showAddModal = false">
        <div class="px-6 py-4 border-b border-slate-700">
            <h2 class="text-lg font-semibold text-white" x-text="editingId ? '编辑订阅' : '添加订阅'"></h2>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">订阅名称</label>
                <input x-model="form.name" type="text" placeholder="例: VIP Edgetunnel"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">订阅 URL</label>
                <input x-model="form.url" type="url" placeholder="https://example.com/sub?token=xxx"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">关联 CF Worker</label>
                <select x-model="form.dns_cf_worker_id"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white focus:outline-none focus:border-brand-500 transition">
                    <option value="">自动匹配（按订阅域名）</option>
                    <template x-for="w in cfWorkers" :key="w.id">
                        <option :value="w.id" x-text="w.name + ' — ' + w.domain"></option>
                    </template>
                </select>
                <p class="text-xs text-slate-500 mt-1">留空则自动按订阅 URL 域名匹配已部署的 Worker</p>
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">绑定套餐组</label>
                <div class="flex flex-wrap gap-2">
                    <button @click="toggleAllGroups()"
                        class="px-3 py-1.5 text-xs rounded-lg border transition"
                        :class="form.group_ids.includes('all') ? 'bg-brand-500/20 border-brand-500 text-brand-400' : 'bg-slate-900 border-slate-600 text-slate-400 hover:border-slate-500'"
                        x-text="'全部套餐组'"></button>
                    <template x-for="group in planGroups" :key="group.id">
                        <button @click="toggleGroup(group.id)"
                            class="px-3 py-1.5 text-xs rounded-lg border transition"
                            :class="form.group_ids.includes(group.id) ? 'bg-brand-500/20 border-brand-500 text-brand-400' : 'bg-slate-900 border-slate-600 text-slate-400 hover:border-slate-500'"
                            x-text="group.name"></button>
                    </template>
                </div>
            </div>
            <div class="flex items-center gap-6">
                <label class="flex items-center gap-2 text-sm text-slate-400 cursor-pointer">
                    <input type="checkbox" x-model="form.enabled" class="rounded border-slate-600 text-brand-500 focus:ring-brand-500">
                    启用
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-400 cursor-pointer">
                    <input type="checkbox" x-model="form.show" class="rounded border-slate-600 text-brand-500 focus:ring-brand-500">
                    显示给用户
                </label>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-700 flex justify-end gap-3">
            <button @click="showAddModal = false" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">取消</button>
            <button @click="saveSubscription()" class="px-4 py-2 text-sm bg-brand-600 hover:bg-brand-500 text-white rounded-lg transition">保存</button>
        </div>
    </div>
</div>

<!-- Server Add Modal -->
<div x-show="showServerModal" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-transition>
    <div class="bg-slate-800 rounded-2xl border border-slate-700 w-full max-w-md mx-4 shadow-2xl" @click.away="showServerModal = false">
        <div class="px-6 py-4 border-b border-slate-700">
            <h2 class="text-lg font-semibold text-white" x-text="editingServerId ? '编辑 Server' : '添加 Server 记录'"></h2>
            <p class="text-xs text-slate-500 mt-1">用于 Worker 流量统计，创建后获得 NODE_ID</p>
        </div>
        <div class="px-6 py-5 space-y-4">
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">Server 名称</label>
                <input x-model="serverForm.name" type="text" placeholder="例: Edgetunnel-HK"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">Host（域名或 IP）</label>
                <input x-model="serverForm.host" type="text" placeholder="例: sub.yourdomain.com"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">端口</label>
                <input x-model="serverForm.port" type="number" value="443" placeholder="443"
                    class="w-full bg-slate-900 border border-slate-600 rounded-lg px-3 py-2 text-sm text-white placeholder-slate-500 focus:outline-none focus:border-brand-500 transition">
            </div>
            <div>
                <label class="block text-sm text-slate-400 mb-1.5">绑定套餐组</label>
                <div class="flex flex-wrap gap-2">
                    <template x-for="group in planGroups" :key="group.id">
                        <button @click="toggleServerGroup(group.id)"
                            class="px-3 py-1.5 text-xs rounded-lg border transition"
                            :class="serverForm.group_ids.includes(group.id) ? 'bg-brand-500/20 border-brand-500 text-brand-400' : 'bg-slate-900 border-slate-600 text-slate-400 hover:border-slate-500'"
                            x-text="group.name"></button>
                    </template>
                </div>
            </div>
        </div>
        <div class="px-6 py-4 border-t border-slate-700 flex justify-end gap-3">
            <button @click="showServerModal = false" class="px-4 py-2 text-sm text-slate-400 hover:text-white transition">取消</button>
            <button @click="editingServerId ? updateServer() : saveServer()" class="px-4 py-2 text-sm bg-brand-600 hover:bg-brand-500 text-white rounded-lg transition" x-text="editingServerId ? '更新' : '创建'"></button>
        </div>
    </div>
</div>

<!-- Preview Injection Modal -->
<div x-show="previewData" class="fixed inset-0 z-50 flex items-center justify-center bg-black/60 backdrop-blur-sm" x-transition>
    <div class="bg-slate-800 rounded-2xl border border-slate-700 w-full max-w-2xl mx-4 shadow-2xl max-h-[80vh] overflow-y-auto" @click.away="previewData = null">
        <div class="px-6 py-4 border-b border-slate-700 flex justify-between items-center sticky top-0 bg-slate-800 z-10">
            <div>
                <h2 class="text-lg font-semibold text-white">注入预览</h2>
                <p class="text-xs text-slate-400 mt-1" x-text="'Worker: ' + (previewData?.worker_domain || '默认') + ' | 节点数: ' + (previewData?.total || 0)"></p>
            </div>
            <button @click="previewData = null" class="text-slate-400 hover:text-white text-xl">&times;</button>
        </div>
        <div class="px-6 py-4">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-xs text-slate-500 uppercase border-b border-slate-700">
                        <th class="px-3 py-2">节点名</th>
                        <th class="px-3 py-2">原始地址</th>
                        <th class="px-3 py-2">注入后地址</th>
                        <th class="px-3 py-2">SNI</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/30">
                    <template x-for="node in previewData?.nodes || []" :key="node.name">
                        <tr class="hover:bg-slate-700/20">
                            <td class="px-3 py-2 text-slate-200 text-xs" x-text="node.name"></td>
                            <td class="px-3 py-2 text-slate-500 font-mono text-xs" x-text="node.original_host"></td>
                            <td class="px-3 py-2 text-emerald-400 font-mono text-xs" x-text="node.injected_host"></td>
                            <td class="px-3 py-2 text-blue-400 font-mono text-xs" x-text="node.sni"></td>
                        </tr>
                    </template>
                </tbody>
            </table>
            <div x-show="!previewData?.nodes || previewData?.nodes.length === 0" class="text-center py-8 text-slate-500 text-sm">
                暂无节点数据
            </div>
        </div>
    </div>
</div>

<!-- Toast -->
<div x-show="toast.show" class="fixed bottom-6 right-6 z-50 fade-in">
    <div class="px-4 py-3 rounded-lg text-sm shadow-lg"
        :class="toast.type === 'success' ? 'bg-emerald-600 text-white' : toast.type === 'error' ? 'bg-red-600 text-white' : 'bg-slate-700 text-slate-200'"
        x-text="toast.message"></div>
</div>

<script>
const SECURE_PATH = '{{ $secure_path ?? "admin" }}';

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

function edgetunnelApp() {
    return {
        subscriptions: [],
        nodeGroups: {},
        loadingNodes: {},
        loading: true,
        syncingId: null,
        testingNode: false,
        expandedSub: null,
        showAddModal: false,
        showServerModal: false,
        editingServerId: null,
        editingId: null,
        planGroups: [],
        cfWorkers: [],
        servers: [],
        form: { name: '', url: '', group_ids: [], dns_cf_worker_id: null, enabled: true, show: true },
        serverForm: { name: '', host: '', port: 443, group_ids: [] },
        toast: { show: false, message: '', type: 'success' },
        userList: [],
        selectedUsers: [],
        batchTargetGroupId: 3,
        previewingId: null,
        previewData: null,

        async init() {
            // 处理 URL ?_t=xxx 参数（登录后跳转：服务器已验证，写入 localStorage 后清理 URL）
            try {
                const url = new URL(window.location.href);
                const urlToken = url.searchParams.get('_t');
                if (urlToken) {
                    localStorage.setItem('XBOARD_ACCESS_TOKEN', JSON.stringify({ value: urlToken }));
                    url.searchParams.delete('_t');
                    window.history.replaceState({}, '', url.toString());
                }
            } catch(e) {}

            await Promise.all([this.fetchSubscriptions(), this.fetchPlanGroups(), this.fetchCfWorkers(), this.fetchServers(), this.fetchUsers()]);
        },

        async fetchSubscriptions() {
            this.loading = true;
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/subscriptions`, {
                    headers: { 'Authorization': getAuthToken() }
                });
                const json = await res.json();
                if (!res.ok) {
                    this.showToast('加载失败 (' + res.status + ')', 'error');
                }
                this.subscriptions = json.data || [];
            } catch (e) {
                this.showToast('加载失败: ' + e.message, 'error');
            }
            this.loading = false;
        },

        async fetchPlanGroups() {
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/server/group/fetch`, {
                    headers: { 'Authorization': getAuthToken() }
                });
                const json = await res.json();
                this.planGroups = (json.data || []).map(g => ({ id: g.id, name: g.name }));
            } catch (e) { /* ignore */ }
        },

        async fetchUsers() {
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/users`, {
                    headers: { 'Authorization': getAuthToken() }
                });
                const json = await res.json();
                this.userList = json.data || [];
            } catch (e) {
                this.showToast('加载用户列表失败', 'error');
            }
        },

        toggleUser(id, checked) {
            if (checked) {
                if (!this.selectedUsers.includes(id)) this.selectedUsers.push(id);
            } else {
                this.selectedUsers = this.selectedUsers.filter(uid => uid !== id);
            }
        },

        toggleAllUsers(checked) {
            this.selectedUsers = checked ? this.userList.map(u => u.id) : [];
        },

        isUserActive(u) {
            if (!u.expired_at) return true;
            return u.expired_at > Math.floor(Date.now() / 1000);
        },

        async batchUpdateGroup() {
            if (this.selectedUsers.length === 0) return;
            const targetName = this.getGroupName(this.batchTargetGroupId);
            if (!confirm(`确定将 ${this.selectedUsers.length} 个用户改为「${targetName}」？`)) return;
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/users/batch-group`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ user_ids: this.selectedUsers, group_id: this.batchTargetGroupId })
                });
                const json = await res.json();
                if (json.data && json.data.affected > 0) {
                    this.showToast(`已更新 ${json.data.affected} 个用户为 ${targetName}`);
                    this.selectedUsers = [];
                    await this.fetchUsers();
                } else {
                    this.showToast('更新失败', 'error');
                }
            } catch (e) {
                this.showToast('更新失败: ' + e.message, 'error');
            }
        },

        async fetchServers() {
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/servers`, {
                    headers: { 'Authorization': getAuthToken() }
                });
                const json = await res.json();
                this.servers = json.data || [];
            } catch (e) {
                this.showToast('加载 Server 记录失败', 'error');
            }
        },

        async fetchCfWorkers() {
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/cf-worker/list`, {
                    headers: { 'Authorization': getAuthToken() }
                });
                const json = await res.json();
                this.cfWorkers = json.data || [];
            } catch (e) {
                // Worker 列表加载失败不影响主功能
            }
        },

        toggleServerGroup(id) {
            const idx = this.serverForm.group_ids.indexOf(id);
            if (idx > -1) {
                this.serverForm.group_ids.splice(idx, 1);
            } else {
                this.serverForm.group_ids.push(id);
            }
        },

        toggleAllGroups() {
            const idx = this.form.group_ids.indexOf('all');
            if (idx > -1) {
                this.form.group_ids.splice(idx, 1);
            } else {
                this.form.group_ids = ['all'];
            }
        },

        toggleGroup(id) {
            const idx = this.form.group_ids.indexOf(id);
            if (idx > -1) {
                this.form.group_ids.splice(idx, 1);
            } else {
                const allIdx = this.form.group_ids.indexOf('all');
                if (allIdx > -1) this.form.group_ids.splice(allIdx, 1);
                this.form.group_ids.push(id);
            }
        },

        getGroupName(id) {
            const group = this.planGroups.find(g => g.id === id);
            return group ? group.name : 'ID:' + id;
        },

        editSubscription(sub) {
            this.editingId = sub.id;
            this.form = {
                name: sub.name,
                url: sub.url,
                group_ids: [...(sub.group_ids || [])],
                dns_cf_worker_id: sub.dns_cf_worker_id || null,
                enabled: sub.enabled !== false,
                show: sub.show !== false,
            };
            this.showAddModal = true;
        },

        async saveSubscription() {
            if (!this.form.name || !this.form.url) {
                this.showToast('请填写名称和URL', 'error');
                return;
            }
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/subscription/save`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ id: this.editingId, ...this.form })
                });
                const json = await res.json();
                if (json.data) {
                    this.showToast(this.editingId ? '更新成功' : '添加成功');
                    this.showAddModal = false;
                    this.editingId = null;
                    this.form = { name: '', url: '', group_ids: [], dns_cf_worker_id: null, enabled: true, show: true };
                    await this.fetchSubscriptions();
                } else {
                    this.showToast('保存失败 (' + res.status + ')', 'error');
                }
            } catch (e) {
                this.showToast('保存失败: ' + e.message, 'error');
            }
        },

        async syncSubscription(id) {
            this.syncingId = id;
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/subscription/sync`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ id })
                });
                const json = await res.json();
                if (json.data && json.data.success) {
                    this.showToast('同步成功，获取 ' + json.data.node_count + ' 个节点');
                    await this.fetchSubscriptions();
                    if (this.expandedSub === id) await this.loadNodes(id);
                } else {
                    this.showToast('同步失败', 'error');
                }
            } catch (e) {
                this.showToast('同步失败: ' + e.message, 'error');
            }
            this.syncingId = null;
        },

        confirmDrop(sub) {
            if (confirm('确定删除订阅 "' + sub.name + '"？所有关联节点也会被删除。')) {
                this.dropSubscription(sub.id);
            }
        },

        async dropSubscription(id) {
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/subscription/drop`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ id })
                });
                if (res.ok) {
                    this.showToast('已删除');
                    await this.fetchSubscriptions();
                } else {
                    this.showToast('删除失败 (' + res.status + ')', 'error');
                }
            } catch (e) {
                this.showToast('删除失败: ' + e.message, 'error');
            }
        },

        async previewInjection(subId) {
            this.previewingId = subId;
            this.previewData = null;
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/subscription/preview`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ subscription_id: subId })
                });
                const json = await res.json();
                if (res.ok) {
                    this.previewData = json.data;
                } else {
                    this.showToast('预览失败 (' + res.status + ')', 'error');
                }
            } catch (e) {
                this.showToast('预览失败: ' + e.message, 'error');
            }
            this.previewingId = null;
        },

        async toggleNodes(subId) {
            if (this.expandedSub === subId) {
                this.expandedSub = null;
            } else {
                this.expandedSub = subId;
                await this.loadNodes(subId);
            }
        },

        async loadNodes(subId) {
            this.loadingNodes[subId] = true;
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/nodes?subscription_id=${subId}`, {
                    headers: { 'Authorization': getAuthToken() }
                });
                const json = await res.json();
                this.nodeGroups[subId] = json.data || [];
            } catch (e) {
                this.showToast('加载节点失败', 'error');
            }
            this.loadingNodes[subId] = false;
        },

        async testSingleNode(node) {
            this.testingNode = true;
            try {
                await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/node/test-latency`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ id: node.id })
                });
                if (this.expandedSub) await this.loadNodes(this.expandedSub);
            } catch (e) {
                this.showToast('测试失败', 'error');
            }
            this.testingNode = false;
        },

        async batchTestNodes(subId) {
            this.testingNode = true;
            try {
                const nodes = this.nodeGroups[subId]?.flatMap(g => g.nodes.map(n => n.id)) || [];
                if (nodes.length === 0) return;
                await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/node/batch-test`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ node_ids: nodes })
                });
                await this.loadNodes(subId);
                this.showToast('批量测试完成');
            } catch (e) {
                this.showToast('测试失败', 'error');
            }
            this.testingNode = false;
        },

        formatTime(ts) {
            if (!ts) return '-';
            const d = new Date(ts.replace(' ', 'T'));
            return d.toLocaleString('zh-CN', { month:'2-digit', day:'2-digit', hour:'2-digit', minute:'2-digit' });
        },

        editServer(srv) {
            this.editingServerId = srv.id;
            this.serverForm = { name: srv.name, host: srv.host, port: srv.port, group_ids: [...(srv.group_ids || [])] };
            this.showServerModal = true;
        },

        async saveServer() {
            if (!this.serverForm.name || !this.serverForm.host) {
                this.showToast('请填写名称和 Host', 'error');
                return;
            }
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/server/save`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify(this.serverForm)
                });
                const json = await res.json();
                if (json.data && json.data.id) {
                    this.showToast('Server 创建成功，NODE_ID = ' + json.data.id);
                    this.showServerModal = false;
                    this.serverForm = { name: '', host: '', port: 443, group_ids: [] };
                    await this.fetchServers();
                } else {
                    this.showToast('创建失败 (' + res.status + ')', 'error');
                }
            } catch (e) {
                this.showToast('创建失败: ' + e.message, 'error');
            }
        },

        async updateServer() {
            if (!this.serverForm.group_ids || this.serverForm.group_ids.length === 0) {
                this.showToast('请至少选择一个套餐组', 'error');
                return;
            }
            try {
                const res = await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/server/update`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ id: this.editingServerId, group_ids: this.serverForm.group_ids })
                });
                if (res.ok) {
                    this.showToast('Server 套餐组已更新');
                    this.showServerModal = false;
                    this.editingServerId = null;
                    this.serverForm = { name: '', host: '', port: 443, group_ids: [] };
                    await this.fetchServers();
                } else {
                    this.showToast('更新失败 (' + res.status + ')', 'error');
                }
            } catch (e) {
                this.showToast('更新失败: ' + e.message, 'error');
            }
        },

        confirmDropServer(srv) {
            if (confirm('确定删除 Server "' + srv.name + '"？(NODE_ID=' + srv.id + ')')) {
                this.dropServer(srv.id);
            }
        },

        async dropServer(id) {
            try {
                await fetch(`/api/v2/${SECURE_PATH}/edgetunnel/server/drop`, {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'Authorization': getAuthToken() },
                    body: JSON.stringify({ id })
                });
                this.showToast('Server 已删除');
                await this.fetchServers();
            } catch (e) {
                this.showToast('删除失败', 'error');
            }
        },

        copyNodeId(id) {
            navigator.clipboard.writeText(String(id));
            this.showToast('NODE_ID ' + id + ' 已复制');
        },

        showToast(msg, type = 'success') {
            this.toast = { show: true, message: msg, type };
            setTimeout(() => this.toast.show = false, 3000);
        }
    }
}
</script>

</body>
</html>
