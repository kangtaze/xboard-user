<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getUserInfo, getSubscribe, getAppVersion, getTrafficLog } from '@/api/user'
import { useAuthStore } from '@/stores/auth'
import type { UserInfo, SubscribeInfo } from '@/api/types'

const { t, locale } = useI18n()
const authStore = useAuthStore()

const userInfo = ref<UserInfo | null>(null)
const subscribeInfo = ref<SubscribeInfo | null>(null)
const loading = ref(true)
const toastMsg = ref('')
const appDownloads = ref<{
  windows_version: string
  windows_download_url: string
  android_version: string
  android_download_url: string
  macos_version: string
  macos_download_url: string
  ios_version: string
  ios_download_url: string
} | null>(null)

// Traffic log (daily stats from API)
const trafficLogs = ref<Array<{record_at: number; d: number; u: number; server_rate: string}>>([])

const loadTrafficLog = async () => {
  try {
    const res = (await getTrafficLog()) as any
    if (Array.isArray(res?.data)) {
      trafficLogs.value = res.data.slice(0, 7)
    }
  } catch { /* silent */ }
}

// --- Formatters ---
function formatBytes(bytes: number): string {
  if (!bytes || bytes <= 0) return '0 GB'
  const gb = bytes / 1024 / 1024 / 1024
  if (gb >= 1000) {
    return (gb / 1000).toFixed(1) + ' TB'
  }
  return gb.toFixed(1) + ' GB'
}

function formatDate(ts: number): string {
  if (!ts) return '--'
  return new Date(ts * 1000).toLocaleDateString(locale.value)
}

function daysUntil(ts: number): string {
  if (!ts) return '--'
  const diff = ts * 1000 - Date.now()
  const days = Math.max(0, Math.ceil(diff / 86400000))
  return days + t('dashboard.fallbacks.dayUnit')
}

// --- Computed ---
const usedTraffic = computed(() => {
  if (!subscribeInfo.value) return 0
  return (subscribeInfo.value.u || 0) + (subscribeInfo.value.d || 0)
})

const totalTraffic = computed(() => {
  return subscribeInfo.value?.transfer_enable || 0
})

const remainingTraffic = computed(() => {
  return Math.max(0, totalTraffic.value - usedTraffic.value)
})

const usagePercent = computed(() => {
  if (!totalTraffic.value) return 0
  return Math.min(100, Math.round((usedTraffic.value / totalTraffic.value) * 100))
})

const email = computed(() => userInfo.value?.email || authStore.user?.email || t('dashboard.fallbacks.email'))
const planName = computed(() => {
  if (subscribeInfo.value?.plan?.name) return subscribeInfo.value.plan.name
  if (userInfo.value?.plan_id) return 'VIP ' + userInfo.value.plan_id
  return t('dashboard.fallbacks.free')
})
const expiryDate = computed(() => formatDate(subscribeInfo.value?.expired_at || userInfo.value?.expired_at || 0))
const expiryDays = computed(() => daysUntil(subscribeInfo.value?.expired_at || userInfo.value?.expired_at || 0))

// --- Subscribe URL helpers ---
const subscribeUrl = computed(() => {
  if (subscribeInfo.value?.subscribe_url) return subscribeInfo.value.subscribe_url
  return ''
})

const token = computed(() => {
  return subscribeInfo.value?.token || ''
})

function buildFormatUrl(flag: string): string {
  if (!subscribeUrl.value && token.value) {
    const origin = window.location.origin
    return `${origin}/api/v1/client/subscribe?token=${token.value}&flag=${flag}`
  }
  if (subscribeUrl.value) {
    const sep = subscribeUrl.value.includes('?') ? '&' : '?'
    return `${subscribeUrl.value}${sep}flag=${flag}`
  }
  return ''
}

// --- Copy to clipboard ---
async function copyToClipboard(text: string, label: string) {
  try {
    if (navigator.clipboard) {
      await navigator.clipboard.writeText(text)
    } else {
      const ta = document.createElement('textarea')
      ta.value = text
      document.body.appendChild(ta)
      ta.select()
      document.execCommand('copy')
      document.body.removeChild(ta)
    }
    showToast(t('dashboard.messages.copied', { label }))
  } catch {
    showToast(t('dashboard.messages.copyFailed'))
  }
}

function showToast(msg: string) {
  toastMsg.value = msg
  setTimeout(() => { toastMsg.value = '' }, 2500)
}

// --- SVG Donut Chart ---
const circumference = 2 * Math.PI * 40 // r=40
const dashOffset = computed(() => {
  return circumference - (circumference * usagePercent.value) / 100
})

// --- Data Loading ---
onMounted(async () => {
  loading.value = true
  try {
    const [userRes, subRes] = await Promise.all([
      getUserInfo(),
      getSubscribe(),
    ])
    userInfo.value = (userRes as any)?.data ?? null
    subscribeInfo.value = (subRes as any)?.data ?? null
  } catch (e) {
    console.error('Dashboard load error:', e)
  } finally {
    loading.value = false
  }

  // Load traffic log (non-blocking)
  loadTrafficLog()

  // Load app download info (non-blocking)
  try {
    const appRes = await getAppVersion()
    appDownloads.value = (appRes as any)?.data ?? null
  } catch (e) {
    // Silently fail - downloads are optional
  }
})
</script>

<template>
  <div class="dashboard-page">
    <!-- Toast -->
    <Transition name="toast">
      <div v-if="toastMsg" class="toast">{{ toastMsg }}</div>
    </Transition>

    <!-- Loading State -->
    <div v-if="loading" class="loading-state">
      <span class="vp-spinner vp-spinner-lg"></span>
      <span>{{ t('common.loading') }}</span>
    </div>

    <template v-else>
      <!-- Subscription Quick Bar -->
      <div class="sub-quick-bar">
        <div class="sub-quick-label">{{ t('dashboard.quickBar.title') }}</div>
        <div class="sub-quick-url">{{ subscribeUrl || t('dashboard.subBar.noLink') }}</div>
        <button class="copy-btn" @click="copyToClipboard(subscribeUrl, t('dashboard.subBar.linkLabel'))">{{ t('dashboard.quickBar.copyLink') }}</button>
        <div class="sub-quick-formats">
          <button class="fmt-chip" @click="copyToClipboard(buildFormatUrl('clashmeta'), t('dashboard.subBar.fmtLabels.clash'))">Clash</button>
          <button class="fmt-chip" @click="copyToClipboard(buildFormatUrl('singbox'), t('dashboard.subBar.fmtLabels.singbox'))">SingBox</button>
          <button class="fmt-chip" @click="copyToClipboard(buildFormatUrl('shadowsocks'), t('dashboard.subBar.fmtLabels.ss'))">SS</button>
          <button class="fmt-chip" @click="copyToClipboard(buildFormatUrl('trojan'), t('dashboard.subBar.fmtLabels.trojan'))">Trojan</button>
          <button class="fmt-chip" @click="copyToClipboard(buildFormatUrl('v2ray'), t('dashboard.subBar.fmtLabels.v2ray'))">V2Ray</button>
        </div>
      </div>

      <!-- Welcome Banner -->
      <div class="welcome-banner">
        <div>
          <h2 class="welcome-title">{{ t('dashboard.welcomeBack', { name: email }) }}</h2>
          <p class="welcome-sub">{{ t('dashboard.planValidUntil', { plan: planName, date: expiryDate }) }}</p>
        </div>
        <button class="renew-btn" @click="$router.push('/dashboard/plans')">{{ t('dashboard.subscribe.title') }}</button>
      </div>

      <!-- App Download Cards -->
      <div class="download-section">
        <div class="download-section-title">
          <span class="material-symbols-outlined">download</span>
          {{ t('dashboard.appDownloads.title') }}
        </div>
        <div class="download-cards">
          <!-- Windows -->
          <div class="dl-card">
            <div class="dl-card-icon">
              <span class="material-symbols-outlined">desktop_windows</span>
            </div>
            <div class="dl-card-info">
              <div class="dl-card-title">{{ t('dashboard.appDownloads.platforms.windows') }}</div>
              <div v-if="appDownloads?.windows_download_url" class="dl-card-version">{{ appDownloads.windows_version || t('dashboard.appDownloads.latestVersion') }}</div>
              <div v-else class="dl-card-version text-muted">{{ t('dashboard.appDownloads.comingSoon') }}</div>
            </div>
            <a v-if="appDownloads?.windows_download_url" :href="appDownloads.windows_download_url" download class="dl-btn dl-btn-primary">
              <span class="material-symbols-outlined">download</span>
              {{ t('dashboard.appDownloads.downloadNow') }}
            </a>
            <span v-else class="dl-btn dl-btn-disabled">{{ t('dashboard.appDownloads.stayTuned') }}</span>
          </div>
          <!-- Android -->
          <div class="dl-card">
            <div class="dl-card-icon">
              <span class="material-symbols-outlined">phone_android</span>
            </div>
            <div class="dl-card-info">
              <div class="dl-card-title">{{ t('dashboard.appDownloads.platforms.android') }}</div>
              <div v-if="appDownloads?.android_download_url" class="dl-card-version">{{ appDownloads.android_version || t('dashboard.appDownloads.latestVersion') }}</div>
              <div v-else class="dl-card-version text-muted">{{ t('dashboard.appDownloads.comingSoon') }}</div>
            </div>
            <a v-if="appDownloads?.android_download_url" :href="appDownloads.android_download_url" download class="dl-btn dl-btn-primary">
              <span class="material-symbols-outlined">download</span>
              {{ t('dashboard.appDownloads.downloadNow') }}
            </a>
            <span v-else class="dl-btn dl-btn-disabled">{{ t('dashboard.appDownloads.stayTuned') }}</span>
          </div>
          <!-- iOS -->
          <div class="dl-card">
            <div class="dl-card-icon">
              <span class="material-symbols-outlined">phone_iphone</span>
            </div>
            <div class="dl-card-info">
              <div class="dl-card-title">{{ t('dashboard.appDownloads.platforms.ios') }}</div>
              <div v-if="appDownloads?.ios_download_url || appDownloads?.macos_download_url" class="dl-card-version">{{ appDownloads.ios_version || appDownloads.macos_version || t('dashboard.appDownloads.latestVersion') }}</div>
              <div v-else class="dl-card-version text-muted">{{ t('dashboard.appDownloads.comingSoon') }}</div>
            </div>
            <a v-if="appDownloads?.ios_download_url || appDownloads?.macos_download_url" :href="appDownloads.ios_download_url || appDownloads.macos_download_url" download class="dl-btn dl-btn-primary">
              <span class="material-symbols-outlined">download</span>
              {{ t('dashboard.appDownloads.downloadNow') }}
            </a>
            <span v-else class="dl-btn dl-btn-disabled">{{ t('dashboard.appDownloads.stayTuned') }}</span>
          </div>
        </div>
      </div>

      <!-- Stats Grid -->
      <div class="stats-grid">
        <div class="stat-card">
          <div class="stat-icon" style="background: #e0f2ff; color: #0066ff;">
            <span class="material-symbols-outlined">data_usage</span>
          </div>
          <div>
            <div class="stat-label">{{ t('dashboard.stats.used') }}</div>
            <div class="stat-value">{{ formatBytes(usedTraffic) }}</div>
            <div class="stat-meta">{{ t('dashboard.stats.thisMonth') }}</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background: #dcfce7; color: #16a34a;">
            <span class="material-symbols-outlined">cloud_done</span>
          </div>
          <div>
            <div class="stat-label">{{ t('dashboard.stats.remaining') }}</div>
            <div class="stat-value">{{ formatBytes(remainingTraffic) }}</div>
            <div class="stat-meta">{{ t('dashboard.stats.totalOf', { total: formatBytes(totalTraffic) }) }}</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background: #fef3c7; color: #ca8a04;">
            <span class="material-symbols-outlined">event</span>
          </div>
          <div>
            <div class="stat-label">{{ t('dashboard.stats.expiry') }}</div>
            <div class="stat-value">{{ expiryDays }}</div>
            <div class="stat-meta">{{ expiryDate }}</div>
          </div>
        </div>
        <div class="stat-card">
          <div class="stat-icon" style="background: #f3e8ff; color: #9333ea;">
            <span class="material-symbols-outlined">devices</span>
          </div>
          <div>
            <div class="stat-label">{{ t('dashboard.stats.devices') }}</div>
            <div class="stat-value">{{ subscribeInfo?.device_limit || t('dashboard.stats.unlimited') }}{{ t('dashboard.stats.deviceUnit') }}</div>
            <div class="stat-meta">{{ subscribeInfo?.device_limit ? t('dashboard.stats.deviceLimit') : t('dashboard.stats.unlimitedDevices') }}</div>
          </div>
        </div>
      </div>

      <!-- Traffic Chart + Recent Connections -->
      <div class="grid-2col gap-6">
        <div class="card">
          <div class="card-header">{{ t('dashboard.traffic.title') }}</div>
          <div class="traffic-chart">
            <svg viewBox="0 0 200 100" class="donut-svg">
              <circle cx="100" cy="50" r="40" fill="none" stroke="#e0e3e5" stroke-width="8" />
              <circle
                cx="100" cy="50" r="40" fill="none" stroke="#0066ff" stroke-width="8"
                :stroke-dasharray="circumference"
                :stroke-dashoffset="dashOffset"
                transform="rotate(-90 100 50)"
                stroke-linecap="round"
              />
              <text x="100" y="46" text-anchor="middle" class="donut-percent">{{ usagePercent }}%</text>
              <text x="100" y="62" text-anchor="middle" class="donut-label">{{ t('dashboard.chart.usedLabel') }}</text>
            </svg>
          </div>
          <div class="traffic-legend">
            <div class="legend-item">
              <span class="legend-dot" style="background: #0066ff;"></span>{{ t('dashboard.chart.legendUsed', { bytes: formatBytes(usedTraffic) }) }}
            </div>
            <div class="legend-item">
              <span class="legend-dot" style="background: #e0e3e5;"></span>{{ t('dashboard.chart.legendRemaining', { bytes: formatBytes(remainingTraffic) }) }}
            </div>
          </div>
        </div>
        <div class="card">
          <div class="card-header">{{ t('dashboard.traffic.chart') }}</div>
          <div class="conn-list">
            <div v-for="(log, i) in trafficLogs" :key="i" class="conn-item">
              <div class="conn-time">{{ formatDate(log.record_at) }}</div>
              <!-- v61e 修复: API 的 d/u 已经是 bytes (TrafficLogResource 直传 v2_stat_user),不再乘 1024*1024 -->
              <div class="conn-node">↓ {{ formatBytes(log.d) }}</div>
              <div class="conn-traffic">↑ {{ formatBytes(log.u) }}</div>
            </div>
            <div v-if="trafficLogs.length === 0" class="conn-empty">{{ t('dashboard.chart.empty') }}</div>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<style scoped>
.dashboard-page {
  padding: 0;
}

/* Loading State */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 80px 0;
  color: #999;
  font-size: 14px;
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

/* === Subscription Quick Bar === */
.sub-quick-bar {
  display: flex;
  align-items: center;
  gap: 12px;
  flex-wrap: wrap;
  padding: 16px 20px;
  background: #fff;
  border-radius: 16px;
  border: 1px solid #e0e3e5;
  margin-bottom: 24px;
}

.sub-quick-label {
  font-size: 13px;
  font-weight: 600;
  color: #191c1e;
  white-space: nowrap;
}

.sub-quick-url {
  flex: 1;
  min-width: 200px;
  padding: 10px 14px;
  background: #f0f4ff;
  border-radius: 10px;
  font-size: 13px;
  font-weight: 600;
  color: #0050cb;
  overflow: hidden;
  text-overflow: ellipsis;
  white-space: nowrap;
}

.sub-quick-formats {
  display: flex;
  gap: 8px;
  flex-wrap: wrap;
}

.copy-btn {
  padding: 8px 20px;
  background: #0066ff;
  color: #fff;
  border: none;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  font-family: 'Inter', sans-serif;
  transition: all 0.15s;
  white-space: nowrap;
}

.copy-btn:hover {
  background: #0050cb;
}

.copy-btn:active {
  transform: scale(0.96);
}

.fmt-chip {
  padding: 8px 16px;
  background: #f7f9fb;
  border: 1px solid #e0e3e5;
  border-radius: 8px;
  font-size: 13px;
  font-weight: 600;
  color: #424656;
  cursor: pointer;
  font-family: 'Inter', sans-serif;
  transition: all 0.15s;
  white-space: nowrap;
}

.fmt-chip:hover {
  background: #0066ff;
  color: #fff;
  border-color: #0066ff;
}

/* === Welcome Banner === */
.welcome-banner {
  background: linear-gradient(135deg, #0066ff 0%, #0050cb 100%);
  border-radius: 16px;
  padding: 32px;
  color: #fff;
  display: flex;
  align-items: center;
  justify-content: space-between;
  margin-bottom: 24px;
}

.welcome-title {
  font-size: 1.5rem;
  font-weight: 700;
  color: #fff;
  line-height: 1.3;
}

.welcome-title .text-primary {
  color: #fff;
  font-weight: 700;
}

.welcome-sub {
  font-size: 14px;
  color: rgba(255, 255, 255, 0.85);
  margin-top: 6px;
}

.welcome-sub .font-bold.text-primary {
  color: #fff;
  font-weight: 700;
}

.renew-btn {
  padding: 12px 28px;
  background: #fff;
  color: #0066ff;
  border: none;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  font-family: 'Inter', sans-serif;
  transition: all 0.2s;
  flex-shrink: 0;
}

.renew-btn:hover {
  transform: translateY(-1px);
  box-shadow: 0 4px 16px rgba(0, 0, 0, 0.2);
}

/* === Stats Grid === */
.stats-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 16px;
  margin-bottom: 24px;
}

.stat-card {
  background: #fff;
  border-radius: 16px;
  padding: 20px;
  border: 1px solid #e0e3e5;
  display: flex;
  align-items: flex-start;
  gap: 16px;
  transition: all 0.2s;
}

.stat-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0, 0, 0, 0.06);
}

.stat-icon {
  width: 48px;
  height: 48px;
  border-radius: 14px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.stat-icon .material-symbols-outlined {
  font-size: 24px;
}

.stat-label {
  font-size: 13px;
  color: #424656;
  margin-bottom: 4px;
}

.stat-value {
  font-size: 28px;
  font-weight: 800;
  color: #191c1e;
  line-height: 1.2;
}

.stat-meta {
  font-size: 12px;
  color: #999;
  margin-top: 4px;
}

/* === Grid Layout === */
.grid-2col {
  display: grid;
  grid-template-columns: 1fr 1fr;
}

.gap-6 {
  gap: 24px;
}

/* === Card === */
.card {
  background: #fff;
  border-radius: 16px;
  padding: 24px;
  border: 1px solid #e0e3e5;
}

.card-header {
  font-size: 16px;
  font-weight: 700;
  color: #191c1e;
  margin-bottom: 20px;
}

/* === Traffic Chart === */
.traffic-chart {
  display: flex;
  justify-content: center;
  padding: 20px 0;
}

.donut-svg {
  width: 100%;
  max-width: 200px;
  height: auto;
}

.donut-percent {
  font-size: 24px;
  font-weight: 700;
  fill: #191c1e;
  font-family: 'Inter', sans-serif;
}

.donut-label {
  font-size: 10px;
  fill: #999;
  font-family: 'Inter', sans-serif;
}

.traffic-legend {
  display: flex;
  justify-content: center;
  gap: 24px;
  margin-top: 8px;
}

.legend-item {
  display: flex;
  align-items: center;
  gap: 8px;
  font-size: 13px;
  color: #424656;
}

.legend-dot {
  width: 10px;
  height: 10px;
  border-radius: 50%;
}

/* === Connection List === */
.conn-list {
  display: flex;
  flex-direction: column;
}

.conn-item {
  display: grid;
  grid-template-columns: 100px 1fr auto;
  align-items: center;
  padding: 14px 0;
  border-bottom: 1px solid #f0f2f4;
  font-size: 13px;
  color: #424656;
}

.conn-item:last-child {
  border-bottom: none;
}

.conn-time {
  color: #999;
  font-size: 12px;
}

.conn-node {
  font-weight: 600;
  color: #191c1e;
}

.conn-traffic {
  font-size: 12px;
  color: #999;
}

.conn-empty {
  padding: 32px 0;
  text-align: center;
  color: #999;
  font-size: 13px;
}

/* === Download Cards === */
.download-section {
  margin-bottom: 24px;
}

.download-section-title {
  display: flex;
  align-items: center;
  gap: 6px;
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 12px;
}

.download-section-title .material-symbols-outlined {
  font-size: 22px;
  color: #0066ff;
}

.download-cards {
  display: flex;
  flex-direction: column;
  gap: 10px;
}

.dl-card {
  display: flex;
  align-items: center;
  gap: 1rem;
  padding: 1rem 1.25rem;
  background: #fff;
  border: 1px solid #E2E8F0;
  border-radius: 16px;
  transition: all .3s cubic-bezier(.34,1.56,.64,1);
  cursor: default;
}

.dl-card:hover {
  transform: translateY(-2px);
  box-shadow: 0 8px 24px rgba(0,102,255,.12);
  border-color: #0066ff;
}

.dl-card-icon {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 52px;
  height: 52px;
  border-radius: 14px;
  background: linear-gradient(135deg,#eef2ff,#e0e7ff);
  flex-shrink: 0;
}

.dl-card-icon .material-symbols-outlined {
  font-size: 28px;
  color: #0066ff;
}

.dl-card-info {
  flex: 1;
  min-width: 0;
}

.dl-card-title {
  font-size: 16px;
  font-weight: 700;
  color: #1e293b;
  margin-bottom: 2px;
}

.dl-card-version {
  font-size: 13px;
  color: #64748b;
}

.dl-card-version.text-muted {
  color: #94a3b8;
}

.dl-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  padding: 8px 20px;
  border-radius: 10px;
  font-size: 14px;
  font-weight: 600;
  text-decoration: none;
  transition: all .25s ease;
  flex-shrink: 0;
  cursor: pointer;
}

.dl-btn-primary {
  background: #0066ff;
  color: #fff;
}

.dl-btn-primary:hover {
  background: #0052cc;
  transform: scale(1.03);
}

.dl-btn-primary .material-symbols-outlined {
  font-size: 18px;
}

.dl-btn-disabled {
  background: #f1f5f9;
  color: #94a3b8;
  cursor: not-allowed;
}

/* === Responsive === */
@media (max-width: 1024px) {
  .stats-grid {
    grid-template-columns: repeat(2, 1fr);
  }

  .grid-2col {
    grid-template-columns: 1fr;
  }
}

@media (max-width: 768px) {
  .stats-grid {
    grid-template-columns: 1fr 1fr;
  }

  .welcome-banner {
    flex-direction: column;
    gap: 16px;
    text-align: center;
  }

  .sub-quick-bar {
    flex-direction: column;
    align-items: stretch;
  }

  .sub-quick-url {
    min-width: unset;
  }

  .sub-quick-formats {
    justify-content: center;
  }
}
</style>