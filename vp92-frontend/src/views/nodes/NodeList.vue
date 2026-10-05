<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { fetchServers, fetchEdgetunnelNodes } from '@/api/user'
import type { ServerNode } from '@/api/types'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()

// --- Search & Filters ---
const searchQuery = ref('')
const regionFilter = ref('全部地区')
const protocolFilter = ref('全部协议')

// --- Toast ---
const toastMsg = ref('')

function showToast(msg: string) {
  toastMsg.value = msg
  setTimeout(() => { toastMsg.value = '' }, 2500)
}

// --- Region flag: Windows Chrome 不渲染国旗 emoji,统一用内嵌 SVG (v61c) ---
const flagSvgs: Record<string, string> = {
  JP: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#fff"/><circle cx="10" cy="7" r="4" fill="#bc002d"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  SG: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#fff"/><rect width="20" height="7" fill="#ED2939"/><circle cx="4.5" cy="3.5" r="2.4" fill="#fff"/><circle cx="5.5" cy="3.5" r="2" fill="#ED2939"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  HK: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#DE2910"/><circle cx="10" cy="7" r="2.6" fill="#fff"/><circle cx="10" cy="3.6" r="1.1" fill="#DE2910"/><circle cx="13.2" cy="6" r="1.1" fill="#DE2910"/><circle cx="12" cy="9.9" r="1.1" fill="#DE2910"/><circle cx="8" cy="9.9" r="1.1" fill="#DE2910"/><circle cx="6.8" cy="6" r="1.1" fill="#DE2910"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  TW: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#FE0000"/><rect width="10" height="7" fill="#000095"/><circle cx="5" cy="3.5" r="1.8" fill="#fff"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  KR: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#fff"/><path d="M10 3.2 a3.8 3.8 0 0 1 0 7.6 a1.9 1.9 0 0 1 0-3.8 a1.9 1.9 0 0 0 0-3.8" fill="#0047A0"/><path d="M10 3.2 a3.8 3.8 0 0 0 0 7.6 a1.9 1.9 0 0 0 0-3.8 a1.9 1.9 0 0 1 0-3.8" fill="#CD2E3A"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  US: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="14" fill="#fff"/><rect y="1.6" width="20" height="1.5" fill="#B22234"/><rect y="4.6" width="20" height="1.5" fill="#B22234"/><rect y="7.6" width="20" height="1.5" fill="#B22234"/><rect y="10.6" width="20" height="1.5" fill="#B22234"/><rect width="9" height="7.5" fill="#3C3B6E"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  DE: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="4.7" fill="#000"/><rect y="4.7" width="20" height="4.7" fill="#DD0000"/><rect y="9.4" width="20" height="4.6" fill="#FFCE00"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  FR: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="6.7" height="14" fill="#0055A4"/><rect x="6.7" width="6.6" height="14" fill="#fff"/><rect x="13.3" width="6.7" height="14" fill="#EF4135"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
  NL: '<svg viewBox="0 0 20 14" width="20" height="14"><rect width="20" height="4.7" fill="#AE1C28"/><rect y="4.7" width="20" height="4.7" fill="#fff"/><rect y="9.4" width="20" height="4.6" fill="#21468B"/><rect width="20" height="14" fill="none" stroke="rgba(0,0,0,.12)"/></svg>',
}

function flagOf(code?: string): string {
  return flagSvgs[code?.toUpperCase() || ''] || '🌐'
}

// --- Region 中文名映射(地区列显示"日本"而不是光秃秃 "JP") ---
const regionNames: Record<string, string> = {
  JP: '日本',
  SG: '新加坡',
  HK: '香港',
  TW: '台湾',
  US: '美国',
  KR: '韩国',
  DE: '德国',
  FR: '法国',
  NL: '荷兰',
}

function regionName(code?: string): string {
  const c = code?.toUpperCase() || ''
  return regionNames[c] ? `${regionNames[c]} ${c}` : (c || '--')
}

function getLatencyClass(latency: number): string {
  if (latency < 0) return 'latency-unknown'
  if (latency <= 100) return 'latency-good'
  if (latency <= 150) return 'latency-mid'
  return 'latency-bad'
}

async function copyNodeConfig(node: ServerNode) {
  showToast(`已复制 ${node.name} 配置`)
}

// --- State ---
const apiNodes = ref<ServerNode[]>([])
const loading = ref(true)

// --- Available filter options ---
const availableRegions = computed(() => {
  const regions = new Set<string>()
  apiNodes.value.forEach(n => {
    if (n.country_code) regions.add(n.country_code.toUpperCase())
  })
  return Array.from(regions)
})

const availableProtocols = computed(() => {
  const protocols = new Set<string>()
  apiNodes.value.forEach(n => {
    if (n.type) protocols.add(n.type)
  })
  return Array.from(protocols)
})

// --- Filtered nodes ---
const filteredNodes = computed(() => {
  const nodes = apiNodes.value
  const q = searchQuery.value.toLowerCase().trim()

  return nodes.filter(n => {
    const nameMatch = !q || n.name.toLowerCase().includes(q) || n.country_code?.toLowerCase().includes(q)
    const regionMatch = regionFilter.value === '全部地区' || n.country_code?.toUpperCase() === regionFilter.value
    const protoMatch = protocolFilter.value === '全部协议' || n.type === protocolFilter.value
    return nameMatch && regionMatch && protoMatch
  })
})

// --- Load data ---
onMounted(async () => {
  loading.value = true
  try {
    const [res, etRes] = await Promise.all([
      fetchServers() as any,
      fetchEdgetunnelNodes() as any
    ])
    const data = res?.data
    const etData = etRes?.data

    const combined: ServerNode[] = []
    // Edgetunnel 节点优先（低延迟排序由后端保证）
    if (Array.isArray(etData)) {
      combined.push(...etData.map((n: any) => ({ ...n, source: 'edgetunnel' } as ServerNode)))
    }
    if (Array.isArray(data)) {
      combined.push(...data.map((n: any) => ({ ...n, source: 'server' } as ServerNode)))
    }
    apiNodes.value = combined
  } catch (e) {
    console.error('加载节点失败:', e)
  } finally {
    loading.value = false
  }
})
</script>

<template>
  <div class="node-list-page">
    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toastMsg" class="toast">{{ toastMsg }}</div>
    </Transition>

    <!-- v61 套餐组 banner (group_id=5 admin 试用时 v-if 自动隐藏) -->
    <header v-if="authStore.user?.group" class="group-banner">
      <div class="banner-inner">
        <span class="banner-label">当前套餐组</span>
        <strong class="banner-name">{{ authStore.user.group.name }}</strong>
        <span class="banner-count">共 {{ apiNodes.length }} 个节点</span>
      </div>
    </header>

    <div class="card">
      <!-- Toolbar -->
      <div class="node-toolbar">
        <input
          v-model="searchQuery"
          type="text"
          placeholder="搜索节点..."
          class="search-input"
        />
        <select v-model="regionFilter" class="filter-select">
          <option value="全部地区">全部地区</option>
          <option v-for="r in availableRegions" :key="r" :value="r">{{ r }}</option>
        </select>
        <select v-model="protocolFilter" class="filter-select">
          <option value="全部协议">全部协议</option>
          <option v-for="p in availableProtocols" :key="p" :value="p">{{ p }}</option>
        </select>
      </div>

      <!-- Loading -->
      <div v-if="loading" class="loading-state">
        <span class="vp-spinner vp-spinner-md"></span>
        <span>加载节点列表...</span>
      </div>

      <!-- Table -->
      <div v-else class="node-table">
        <table>
          <thead>
            <tr>
              <th>节点名称</th>
              <th>地区</th>
              <th>状态</th>
              <th>延迟</th>
              <th>协议</th>
              <th>倍率</th>
              <th>操作</th>
            </tr>
          </thead>
          <tbody>
            <!-- API nodes -->
            <tr v-for="node in filteredNodes" :key="node.id">
              <td>
                <div class="node-name">
                  <span class="node-flag" v-html="flagOf(node.country_code)"></span>
                  {{ node.name }}
                </div>
              </td>
              <td>{{ regionName(node.country_code) }}</td>
              <td>
                <span class="status-dot status-good"></span>正常
              </td>
              <td :class="getLatencyClass((node as any).latency)"><span class="latency-pill">{{ (node as any).latency > 0 ? (node as any).latency + ' ms' : '-- ms' }}</span></td>
              <td><span class="protocol-tag">{{ node.type }}</span></td>
              <td>×{{ node.rate || 1 }}</td>
              <td>
                <button class="table-btn" @click="copyNodeConfig(node)">复制</button>
              </td>
            </tr>
            <!-- Empty state -->
            <tr v-if="filteredNodes.length === 0">
              <td colspan="7" class="empty-state">暂无可用节点，请联系管理员</td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>

<style scoped>
.node-list-page {
  padding: 0;
}

/* === v61 Group Banner === */
.group-banner {
  margin-bottom: 16px;
  background: linear-gradient(135deg, #0066ff 0%, #4f46e5 100%);
  border-radius: 16px;
  padding: 18px 24px;
  color: #fff;
  box-shadow: 0 4px 14px rgba(0, 102, 255, 0.18);
}
.banner-inner {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
}
.banner-label {
  font-size: 13px;
  opacity: 0.85;
  letter-spacing: 0.04em;
}
.banner-name {
  font-size: 22px;
  font-weight: 700;
  padding: 4px 12px;
  background: rgba(255, 255, 255, 0.18);
  border-radius: 8px;
}
.banner-count {
  margin-left: auto;
  font-size: 13px;
  opacity: 0.85;
}

/* Toast */
.toast {
  position: fixed;
  top: 20px;
  right: 20px;
  z-index: 9999;
  padding: 14px 24px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 600;
  background: #191c1e;
  color: #fff;
  pointer-events: none;
}

.toast-enter-active,
.toast-leave-active {
  transition: opacity 0.3s, transform 0.3s;
}

.toast-enter-from,
.toast-leave-to {
  opacity: 0;
  transform: translateY(-10px);
}

/* === Card === */
.card {
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #e0e3e5;
}

/* === Loading === */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 60px 0;
  color: #999;
  font-size: 14px;
}

/* === Node Toolbar === */
.node-toolbar {
  display: flex;
  gap: 12px;
  margin-bottom: 20px;
  flex-wrap: wrap;
}

.search-input {
  flex: 1;
  min-width: 200px;
  padding: 10px 16px;
  border: 1.5px solid #e0e3e5;
  border-radius: 10px;
  font-size: 14px;
  font-family: 'Inter', sans-serif;
  outline: none;
  transition: all 0.2s;
  color: #191c1e;
  background: #fff;
}

.search-input:focus {
  border-color: #0066ff;
  box-shadow: 0 0 0 3px rgba(0, 102, 255, 0.08);
}

.filter-select {
  padding: 10px 14px;
  border: 1.5px solid #e0e3e5;
  border-radius: 10px;
  font-size: 13px;
  font-family: 'Inter', sans-serif;
  background: #fff;
  color: #191c1e;
  outline: none;
  cursor: pointer;
}

/* === Node Table === */
.node-table {
  overflow-x: auto;
}

.node-table table {
  width: 100%;
  border-collapse: collapse;
}

.node-table th {
  padding: 12px 16px;
  text-align: left;
  font-size: 12px;
  font-weight: 600;
  color: #999;
  text-transform: uppercase;
  letter-spacing: 0.05em;
  border-bottom: 2px solid #e0e3e5;
}

.node-table td {
  padding: 14px 16px;
  font-size: 14px;
  color: #191c1e;
  border-bottom: 1px solid #f0f2f4;
}

.node-table tr:hover td {
  background: #f8faff;
}

.node-name {
  font-weight: 600;
  display: flex;
  align-items: center;
  gap: 8px;
}

.node-flag {
  display: inline-flex;
  align-items: center;
  flex-shrink: 0;
  border-radius: 2px;
  overflow: hidden;
}
.node-flag svg {
  display: block;
}

/* === Status Dots === */
.status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  display: inline-block;
  margin-right: 8px;
}

.status-good {
  background: #22c55e;
  box-shadow: 0 0 6px rgba(34, 197, 94, 0.4);
}

.status-mid {
  background: #eab308;
  box-shadow: 0 0 6px rgba(234, 179, 8, 0.4);
}

.status-bad {
  background: #ef4444;
  box-shadow: 0 0 6px rgba(239, 68, 68, 0.4);
}

/* === Latency Colors === */
.latency-unknown .latency-pill { color: #999; font-weight: 400; background: #f0f2f4; }
.latency-pill {
  display: inline-block;
  padding: 3px 10px;
  border-radius: 999px;
  font-size: 12.5px;
}
.latency-good .latency-pill {
  color: #15803d;
  background: #dcfce7;
  font-weight: 600;
}

.latency-mid .latency-pill {
  color: #a16207;
  background: #fef9c3;
  font-weight: 600;
}

.latency-bad .latency-pill {
  color: #b91c1c;
  background: #fee2e2;
  font-weight: 600;
}

/* === Protocol Tag === */
.protocol-tag {
  padding: 3px 10px;
  background: #f0f4ff;
  color: #0050cb;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
}

/* === Table Button === */
.table-btn {
  padding: 6px 14px;
  background: none;
  border: 1.5px solid #e0e3e5;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #424656;
  cursor: pointer;
  font-family: 'Inter', sans-serif;
  transition: all 0.15s;
}

.table-btn:hover {
  border-color: #0066ff;
  color: #0066ff;
}

/* === Empty State === */
.empty-state {
  text-align: center;
  color: #999;
  padding: 48px 0;
  font-size: 14px;
}

/* === Responsive === */
@media (max-width: 768px) {
  .node-toolbar {
    flex-direction: column;
  }

  .search-input {
    min-width: unset;
  }
}
</style>
