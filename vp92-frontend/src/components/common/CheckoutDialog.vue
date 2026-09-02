<script setup lang="ts">
import { ref, watch, onBeforeUnmount, nextTick, computed } from 'vue'
import { useI18n } from 'vue-i18n'
import { getPaymentMethods, checkoutOrder, checkOrder, fetchOrders, cancelOrder, getOrderDetail } from '@/api'
import QRCode from 'qrcode'
import type { PaymentMethod, Plan } from '@/api/types'

const props = defineProps<{
  visible: boolean
  tradeNo: string
  pendingConflict?: boolean
  selectedPlan?: Plan | null
  orderAmount?: number       // 订单实际金额（分）
  orderPeriod?: string       // 订单周期（如 'year_price'）
}>()

const emit = defineEmits<{
  close: []
  success: []
  cancelOld: []
}>()

const { t } = useI18n()

// --- Brand config ---
const brandConfig: Record<string, { label: string; color: string; bg: string; logo: string }> = {
  '支付宝': { label: '支付宝', color: '#1677ff', bg: '#1677ff', logo: 'Alipay' },
  '微信支付': { label: '微信支付', color: '#07c160', bg: '#07c160', logo: 'W' },
  'WeChat Pay': { label: 'WeChat Pay', color: '#07c160', bg: '#07c160', logo: 'W' },
  'Alipay': { label: 'Alipay', color: '#1677ff', bg: '#1677ff', logo: 'Alipay' },
  'Stripe': { label: 'Stripe', color: '#635bff', bg: '#635bff', logo: 'S' },
  'PayPal': { label: 'PayPal', color: '#003087', bg: '#003087', logo: 'P' },
  'USDT': { label: 'USDT', color: '#26a17b', bg: '#26a17b', logo: 'T' },
}

function getBrand(name: string) {
  return brandConfig[name] || { label: name || '?', color: '#666', bg: '#666', logo: (name || '?').charAt(0) }
}

const methods = ref<PaymentMethod[]>([])
const loading = ref(false)
const selectedMethod = ref<number | null>(null)
const processing = ref(false)
const errorMsg = ref<string | null>(null)

// QR code state
const qrCodeUrl = ref<string | null>(null)
const qrCanvasRef = ref<HTMLCanvasElement | null>(null)
const polling = ref(false)
const pollTimer = ref<ReturnType<typeof setInterval> | null>(null)
const qrExpired = ref(false)
const QR_EXPIRE_SECONDS = 300

// Pending conflict
const cancellingOld = ref(false)
const pendingTradeNo = ref<string | null>(null)

// Countdown timer for QR expiry
const countdownText = ref('')

// Current step: 'pay' | 'qrcode' | 'success'
const step = ref<'pay' | 'qrcode' | 'success'>('pay')

// Actual order data (fetched from API, overrides plan estimate)
const actualOrderAmount = ref<number | null>(null)
const actualOrderPeriod = ref<string | null>(null)

// Watch dialog open
watch(() => props.visible, async (val) => {
  if (val) {
    resetState()
    await loadMethods()
    await fetchOrderData()
    if (methods.value.length === 1 && !selectedMethod.value) {
      selectedMethod.value = methods.value[0].id
    }
  } else {
    stopPolling()
  }
}, { immediate: true })

// Watch tradeNo changes while dialog is open (e.g. after cancelling old order)
watch(() => props.tradeNo, async (newVal) => {
  if (props.visible && newVal) {
    actualOrderAmount.value = null
    actualOrderPeriod.value = null
    await fetchOrderData()
  }
})

function resetState() {
  selectedMethod.value = null
  errorMsg.value = null
  qrCodeUrl.value = null
  qrExpired.value = false
  pendingTradeNo.value = null
  actualOrderAmount.value = null
  actualOrderPeriod.value = null
  step.value = 'pay'
  stopPolling()
}

async function fetchOrderData() {
  if (props.pendingConflict) {
    try {
      const ordersRes = await fetchOrders() as any
      const orders = ordersRes?.data || []
      const pending = orders.find((o: any) => o.status === 0)
      if (pending?.trade_no) {
        pendingTradeNo.value = pending.trade_no
        actualOrderAmount.value = pending.total_amount
        actualOrderPeriod.value = pending.period
      }
    } catch { /* ignore */ }
  }
  const tradeNoToFetch = props.tradeNo || pendingTradeNo.value
  if (tradeNoToFetch && !props.pendingConflict) {
    try {
      const detail = await getOrderDetail(tradeNoToFetch) as any
      const order = detail?.data?.data ?? detail?.data
      if (order) {
        actualOrderAmount.value = order.total_amount
        actualOrderPeriod.value = order.period
      }
    } catch { /* ignore */ }
  }
}

onBeforeUnmount(() => {
  stopPolling()
})

async function loadMethods() {
  loading.value = true
  errorMsg.value = null
  try {
    const res = await getPaymentMethods() as any
    const data = res.data
    methods.value = Array.isArray(data) ? data : (data?.data ?? [])
    if (methods.value.length === 0) {
      errorMsg.value = t('checkout.noMethods')
    }
  } catch {
    errorMsg.value = t('common.message.serverError')
  } finally {
    loading.value = false
  }
}

function stopPolling() {
  if (pollTimer.value) {
    clearInterval(pollTimer.value)
    pollTimer.value = null
  }
  polling.value = false
  countdownText.value = ''
}

function startPolling() {
  polling.value = true
  qrExpired.value = false
  let elapsed = 0
  pollTimer.value = setInterval(async () => {
    elapsed += 3
    const remaining = QR_EXPIRE_SECONDS - elapsed
    if (remaining <= 0) {
      stopPolling()
      qrExpired.value = true
      return
    }
    const min = Math.floor(remaining / 60)
    const sec = remaining % 60
    countdownText.value = min > 0
      ? t('payment.countdownMinSec', { min, sec: sec.toString().padStart(2, '0') })
      : t('payment.countdownSec', { sec })
    try {
      const tradeNo = pendingTradeNo.value || props.tradeNo
      // 后端 ApiResponse::success 返回 {status, message, data}
      // axios 拦截器 (client.ts) 已解一层 response.data，所以 res.data 就是订单 status 数字
      // 之前写的 res?.data?.data 多解一层，导致永远是 undefined
      const res = await checkOrder(tradeNo) as any
      const status = res?.data
      // 支付成功后 OrderService::paid() 立刻把 status 设为 1 (处理中)
      // 然后 OrderHandleJob 异步推到 3 (已完成)。前端 3 秒轮询大概率落在 1，所以 >= 1 都视为成功
      if (typeof status === 'number' && status >= 1) {
        stopPolling()
        step.value = 'success'
        setTimeout(() => {
          emit('success')
          emit('close')
        }, 1500)
      }
    } catch {
      // ignore
    }
  }, 3000)
}

async function renderQrCode(text: string) {
  qrCodeUrl.value = text
  await nextTick()
  const canvas = qrCanvasRef.value
  if (canvas && text) {
    try {
      await QRCode.toCanvas(canvas, text, {
        width: 200,
        margin: 1,
        color: { dark: '#000000', light: '#ffffff' }
      })
    } catch (e) {
      console.error('QR render error', e)
    }
  }
}

async function doCheckout(tradeNo: string) {
  processing.value = true
  errorMsg.value = null
  try {
    const res = await checkoutOrder({
      trade_no: tradeNo,
      method: String(selectedMethod.value)
    }) as any
    const type = res?.data?.type ?? res?.type
    const data = res?.data?.data ?? res?.data

    if (type === 1) {
      window.location.href = data
      return
    }
    if (type === 0) {
      processing.value = false
      step.value = 'qrcode'
      const isMobile = /Android|iPhone|iPad|iPod|Mobile/i.test(navigator.userAgent)
      if (isMobile) {
        window.location.href = 'alipays://platformapi/startapp?saId=10000007&qrcode=' + encodeURIComponent(data)
      } else {
        await renderQrCode(data)
      }
      startPolling()
      return
    }
    if (type === -1) {
      step.value = 'success'
      setTimeout(() => {
        emit('success')
        emit('close')
      }, 1500)
      return
    }
    errorMsg.value = t('checkout.unknownType')
  } catch (e: any) {
    errorMsg.value = t('checkout.timeoutRetry')
  } finally {
    processing.value = false
  }
}

async function handlePay() {
  if (selectedMethod.value === null || !props.tradeNo) return
  await doCheckout(props.tradeNo)
}

async function handlePayPending() {
  if (!pendingTradeNo.value || selectedMethod.value === null) return
  await doCheckout(pendingTradeNo.value)
}

async function handleRetry() {
  const tradeNo = pendingTradeNo.value || props.tradeNo
  if (!tradeNo || selectedMethod.value === null) return
  qrExpired.value = false
  await doCheckout(tradeNo)
}

async function handleCancelOldOrder() {
  if (cancellingOld.value || !pendingTradeNo.value) return
  cancellingOld.value = true
  errorMsg.value = null
  try {
    await cancelOrder(pendingTradeNo.value)
    pendingTradeNo.value = null
    actualOrderAmount.value = null
    actualOrderPeriod.value = null
    emit('cancelOld')
  } catch {
    errorMsg.value = t('payment.cancelFailed')
  } finally {
    cancellingOld.value = false
  }
}

function close() {
  stopPolling()
  qrCodeUrl.value = null
  step.value = 'pay'
  emit('close')
}

function handleBackdrop(e: MouseEvent) {
  if (e.target === e.currentTarget && !processing.value && !polling.value) {
    close()
  }
}

// Format price from plan
function formatPrice(cents: number): string {
  if (!cents && cents !== 0) return '0'
  const val = cents / 100
  return val % 1 === 0 ? val.toFixed(0) : val.toFixed(2)
}

function getPeriodLabel(period: string): string {
  const keyMap: Record<string, string> = {
    month_price: 'plans.periods.monthly',
    quarter_price: 'plans.periods.quarterly',
    half_year_price: 'plans.periods.halfYear',
    year_price: 'plans.periods.yearly',
    two_year_price: 'plans.periods.twoYear',
    three_year_price: 'plans.periods.threeYear',
    onetime: 'plans.periods.onetime',
    // 新版格式
    monthly: 'plans.periods.monthly',
    quarterly: 'plans.periods.quarterly',
    half_yearly: 'plans.periods.halfYear',
    yearly: 'plans.periods.yearly',
    two_yearly: 'plans.periods.twoYear',
    three_yearly: 'plans.periods.threeYear',
    reset_traffic: 'plans.periods.resetTraffic',
  }
  const key = keyMap[period]
  return key ? t(key) : period
}

const planPrice = computed(() => {
  // 优先使用订单实际金额和周期
  if (actualOrderAmount.value !== null) {
    const label = actualOrderPeriod.value ? getPeriodLabel(actualOrderPeriod.value) : ''
    return { amount: actualOrderAmount.value, label }
  }
  if (props.orderAmount !== undefined && props.orderAmount !== null) {
    const label = props.orderPeriod ? getPeriodLabel(props.orderPeriod) : ''
    return { amount: props.orderAmount, label }
  }
  // 兜底：从 plan 价格字段取第一个非零值
  if (!props.selectedPlan) return null
  const p = props.selectedPlan
  const prices = [
    { key: 'month_price', label: t('plans.periods.monthly') },
    { key: 'quarter_price', label: t('plans.periods.quarterly') },
    { key: 'half_year_price', label: t('plans.periods.halfYear') },
    { key: 'year_price', label: t('plans.periods.yearly') },
  ]
  for (const pr of prices) {
    if ((p as any)[pr.key] > 0) {
      return { amount: (p as any)[pr.key], label: pr.label }
    }
  }
  return null
})
</script>

<template>
  <Transition name="modal">
    <div v-if="visible" class="pay-backdrop" @click="handleBackdrop">
      <div class="pay-dialog animate-scale-in" @click.stop>

        <!-- ===== STEP 1: PAY METHOD SELECTION ===== -->
        <template v-if="step === 'pay' && !loading && methods.length > 0">
          <!-- Header -->
          <div class="pay-header">
            <h2 class="pay-title">{{ t('payment.cashierTitle') }}</h2>
            <button class="pay-close" @click="close">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Order Summary -->
          <div v-if="selectedPlan" class="pay-order-summary">
            <div class="pay-order-row">
              <span class="pay-order-label">{{ t('payment.product') }}</span>
              <span class="pay-order-value">{{ selectedPlan.name }}</span>
            </div>
            <div v-if="planPrice" class="pay-order-row">
              <span class="pay-order-label">{{ t('payment.planLabel') }}</span>
              <span class="pay-order-value">{{ planPrice.label }}</span>
            </div>
            <div v-if="planPrice" class="pay-order-row pay-order-total">
              <span class="pay-order-label">{{ t('payment.total') }}</span>
              <span class="pay-order-amount">¥{{ formatPrice(planPrice.amount) }}</span>
            </div>
          </div>

          <!-- Pending order banner -->
          <div v-if="pendingTradeNo" class="pay-pending">
            <div class="pay-pending-left">
              <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#f59e0b" stroke-width="2"><path d="M12 9v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
              <span>{{ t('payment.unpaidHint') }}</span>
            </div>
            <button class="pay-pending-btn" :disabled="cancellingOld" @click="handleCancelOldOrder">
              {{ cancellingOld ? t('payment.cancelling') : t('payment.cancelOld') }}
            </button>
          </div>

          <!-- Payment Method (clean card) -->
          <div class="pay-method-section">
            <div class="pay-section-label">{{ t('payment.sectionLabel') }}</div>
            <div
              v-for="m in methods"
              :key="m.id"
              class="pay-method-card"
              :class="{ 'pay-method-card--active': selectedMethod === m.id }"
              @click="selectedMethod = m.id"
            >
              <div class="pay-method-logo" :style="{ background: getBrand(m.name).bg }">
                <span class="pay-method-logo-text">{{ getBrand(m.name).logo }}</span>
              </div>
              <span class="pay-method-name">{{ m.name }}</span>
              <svg v-if="selectedMethod === m.id" class="pay-method-check" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="#1677ff" stroke-width="2.5"><path d="M5 13l4 4L19 7"/></svg>
            </div>
          </div>

          <!-- Error -->
          <div v-if="errorMsg" class="pay-error">
            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
            <span>{{ errorMsg }}</span>
          </div>

          <!-- Pay Button -->
          <button
            class="pay-btn"
            :class="{ 'pay-btn--loading': processing }"
            :disabled="selectedMethod === null || processing"
            @click="pendingTradeNo ? handlePayPending() : handlePay()"
          >
            <span v-if="processing" class="pay-btn-spinner"></span>
            <span v-else>{{ t('checkout.payNow') }} ¥{{ planPrice ? formatPrice(planPrice.amount) : '...' }}</span>
          </button>
          <p v-if="processing" class="pay-hint">
            {{ t('payment.connecting') }}
          </p>
        </template>

        <!-- ===== STEP 2: QR CODE ===== -->
        <template v-if="step === 'qrcode'">
          <div class="pay-header">
            <h2 class="pay-title">{{ t('payment.qrTitle') }}</h2>
            <button class="pay-close" @click="close">
              <svg width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M18 6L6 18M6 6l12 12"/></svg>
            </button>
          </div>

          <!-- Order amount -->
          <div class="pay-qr-amount">
            <span class="pay-qr-amount-label">{{ t('payment.amount') }}</span>
            <span class="pay-qr-amount-value">¥{{ planPrice ? formatPrice(planPrice.amount) : '...' }}</span>
          </div>

          <!-- QR Code Card -->
          <div class="pay-qr-card">
            <canvas ref="qrCanvasRef" class="pay-qr-canvas" />
            <!-- Expired overlay -->
            <div v-if="qrExpired" class="pay-qr-overlay">
              <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#999" stroke-width="1.5"><path d="M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/><path d="M12 8v4m0 4h.01"/></svg>
              <p class="pay-qr-overlay-text">{{ t('payment.expired') }}</p>
              <button class="pay-qr-refresh" @click="handleRetry">
                <svg width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M1 4v6h6M23 20v-6h-6"/><path d="M20.49 9A9 9 0 005.64 5.64L1 10m22 4l-4.64 4.36A9 9 0 013.51 15"/></svg>
                {{ t('payment.refresh') }}
              </button>
            </div>
          </div>

          <!-- Scan hint -->
          <div class="pay-qr-hint">
            <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="#1677ff" stroke-width="1.5"><path d="M3 7V5a2 2 0 012-2h2M17 3h2a2 2 0 012 2v2M21 17v2a2 2 0 01-2 2h-2M7 21H5a2 2 0 01-2-2v-2"/><rect x="7" y="7" width="10" height="10" rx="1"/></svg>
            <span v-html="t('payment.scanWithAlipay')"></span>
          </div>

          <!-- Waiting status -->
          <div v-if="polling && !qrExpired" class="pay-qr-status">
            <span class="pay-qr-status-dot"></span>
            <span>{{ t('payment.waitingConfirm') }}</span>
            <span v-if="countdownText" class="pay-qr-timer">{{ countdownText }}</span>
          </div>
        </template>

        <!-- ===== STEP 3: SUCCESS ===== -->
        <template v-if="step === 'success'">
          <div class="pay-success">
            <div class="pay-success-icon">
              <svg width="48" height="48" viewBox="0 0 24 24" fill="none" stroke="#22c55e" stroke-width="2"><circle cx="12" cy="12" r="10"/><path d="M8 12l3 3 5-5"/></svg>
            </div>
            <h3 class="pay-success-title">{{ t('checkout.success') }}</h3>
            <p class="pay-success-desc">{{ t('payment.activating') }}</p>
          </div>
        </template>

        <!-- ===== LOADING STATE ===== -->
        <div v-if="loading" class="pay-loading">
          <span class="pay-loading-spinner"></span>
          <span>{{ t('common.loading') }}</span>
        </div>

        <!-- ===== ERROR STATE ===== -->
        <div v-if="errorMsg && !selectedMethod && !loading" class="pay-error-state">
          <svg width="32" height="32" viewBox="0 0 24 24" fill="none" stroke="#ef4444" stroke-width="1.5"><circle cx="12" cy="12" r="10"/><path d="M15 9l-6 6M9 9l6 6"/></svg>
          <p>{{ errorMsg }}</p>
          <button class="pay-retry-btn" @click="loadMethods">{{ t('common.retry') }}</button>
        </div>

      </div>
    </div>
  </Transition>
</template>

<style scoped>
/* === Backdrop === */
.pay-backdrop {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.5);
  padding: 1rem;
}

/* === Dialog === */
.pay-dialog {
  width: 100%;
  max-width: 420px;
  max-height: 90vh;
  background: #fff;
  border-radius: 16px;
  padding: 0;
  box-shadow: 0 20px 60px rgba(0, 0, 0, 0.3);
  overflow-y: auto;
}

/* === Header === */
.pay-header {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 20px 24px 0;
}

.pay-title {
  font-size: 18px;
  font-weight: 700;
  color: #000;
  margin: 0;
}

.pay-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: none;
  border-radius: 50%;
  background: #f5f5f5;
  color: #666;
  cursor: pointer;
  transition: all 0.2s;
}

.pay-close:hover {
  background: #eee;
  color: #333;
}

/* === Order Summary === */
.pay-order-summary {
  margin: 16px 24px;
  padding: 16px;
  background: #f9fafb;
  border-radius: 12px;
}

.pay-order-row {
  display: flex;
  justify-content: space-between;
  align-items: center;
  padding: 6px 0;
}

.pay-order-label {
  font-size: 14px;
  color: #666;
}

.pay-order-value {
  font-size: 14px;
  color: #000;
  font-weight: 500;
}

.pay-order-total {
  border-top: 1px solid #e5e7eb;
  margin-top: 8px;
  padding-top: 12px;
}

.pay-order-amount {
  font-size: 22px;
  font-weight: 800;
  color: #000;
}

/* === Pending Banner === */
.pay-pending {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 24px 12px;
  padding: 10px 12px;
  background: #fffbeb;
  border: 1px solid #fde68a;
  border-radius: 8px;
  font-size: 13px;
  color: #92400e;
}

.pay-pending-left {
  display: flex;
  align-items: center;
  gap: 6px;
  flex: 1;
  min-width: 0;
}

.pay-pending-btn {
  flex-shrink: 0;
  padding: 4px 10px;
  border: 1px solid #f59e0b;
  border-radius: 6px;
  background: #fff;
  color: #d97706;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.pay-pending-btn:hover:not(:disabled) {
  background: #fef3c7;
}

.pay-pending-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
}

/* === Payment Method Section === */
.pay-method-section {
  margin: 0 24px 16px;
}

.pay-section-label {
  font-size: 14px;
  font-weight: 600;
  color: #000;
  margin-bottom: 10px;
}

.pay-method-card {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 14px 16px;
  border: 2px solid #e5e7eb;
  border-radius: 12px;
  background: #fff;
  cursor: pointer;
  transition: all 0.2s;
}

.pay-method-card:hover {
  border-color: #bfdbfe;
  background: #f0f7ff;
}

.pay-method-card--active {
  border-color: #1677ff !important;
  background: #eff6ff !important;
}

.pay-method-logo {
  width: 36px;
  height: 36px;
  border-radius: 8px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.pay-method-logo-text {
  color: #fff;
  font-size: 12px;
  font-weight: 800;
  font-family: 'Inter', sans-serif;
}

.pay-method-name {
  flex: 1;
  font-size: 15px;
  font-weight: 600;
  color: #000;
}

.pay-method-check {
  flex-shrink: 0;
}

/* === Error === */
.pay-error {
  display: flex;
  align-items: center;
  gap: 8px;
  margin: 0 24px 12px;
  padding: 10px 12px;
  background: #fef2f2;
  border: 1px solid #fecaca;
  border-radius: 8px;
  font-size: 13px;
  color: #dc2626;
}

/* === Pay Button === */
.pay-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  width: calc(100% - 48px);
  margin: 8px 24px 0;
  padding: 14px;
  border: none;
  border-radius: 12px;
  background: linear-gradient(135deg, #1677ff 0%, #0958d9 100%);
  color: #fff;
  font-size: 16px;
  font-weight: 700;
  cursor: pointer;
  transition: all 0.2s;
  box-shadow: 0 4px 12px rgba(22, 119, 255, 0.3);
}

.pay-btn:hover:not(:disabled) {
  box-shadow: 0 6px 20px rgba(22, 119, 255, 0.4);
  transform: translateY(-1px);
}

.pay-btn:active:not(:disabled) {
  transform: translateY(0);
}

.pay-btn:disabled {
  opacity: 0.5;
  cursor: not-allowed;
  transform: none;
  box-shadow: none;
}

.pay-btn--loading {
  pointer-events: none;
}

.pay-btn-spinner {
  width: 20px;
  height: 20px;
  border: 2.5px solid rgba(255, 255, 255, 0.3);
  border-top-color: #fff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

@keyframes spin {
  to { transform: rotate(360deg); }
}

.pay-hint {
  text-align: center;
  font-size: 12px;
  color: #999;
  margin: 8px 24px 20px;
}

/* === QR Code Step === */
.pay-qr-amount {
  text-align: center;
  padding: 20px 24px 16px;
}

.pay-qr-amount-label {
  display: block;
  font-size: 14px;
  color: #666;
  margin-bottom: 4px;
}

.pay-qr-amount-value {
  font-size: 32px;
  font-weight: 800;
  color: #000;
}

.pay-qr-card {
  position: relative;
  width: 220px;
  height: 220px;
  margin: 0 auto;
  padding: 10px;
  background: #fff;
  border: 1px solid #e5e7eb;
  border-radius: 12px;
  box-shadow: 0 2px 8px rgba(0, 0, 0, 0.06);
}

.pay-qr-canvas {
  display: block;
  width: 200px;
  height: 200px;
}

.pay-qr-overlay {
  position: absolute;
  inset: 0;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 8px;
  background: rgba(255, 255, 255, 0.95);
  border-radius: 12px;
}

.pay-qr-overlay-text {
  font-size: 14px;
  color: #666;
  margin: 0;
}

.pay-qr-refresh {
  display: flex;
  align-items: center;
  gap: 4px;
  padding: 8px 16px;
  border: 1px solid #1677ff;
  border-radius: 8px;
  background: #fff;
  color: #1677ff;
  font-size: 13px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.pay-qr-refresh:hover {
  background: #eff6ff;
}

.pay-qr-hint {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 6px;
  margin-top: 16px;
  font-size: 14px;
  color: #333;
}

.pay-qr-hint strong {
  color: #1677ff;
  font-weight: 700;
}

.pay-qr-status {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  margin: 12px 24px 24px;
  font-size: 13px;
  color: #666;
}

.pay-qr-status-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #22c55e;
  animation: pulse-dot 1.5s ease-in-out infinite;
}

@keyframes pulse-dot {
  0%, 100% { opacity: 1; }
  50% { opacity: 0.3; }
}

.pay-qr-timer {
  font-size: 12px;
  color: #999;
  margin-left: 4px;
}

/* === Success === */
.pay-success {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  padding: 48px 24px;
  text-align: center;
}

.pay-success-icon {
  margin-bottom: 16px;
}

.pay-success-title {
  font-size: 20px;
  font-weight: 700;
  color: #000;
  margin: 0 0 8px;
}

.pay-success-desc {
  font-size: 14px;
  color: #666;
  margin: 0;
}

/* === Loading === */
.pay-loading {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 60px 24px;
  color: #999;
  font-size: 14px;
}

.pay-loading-spinner {
  width: 32px;
  height: 32px;
  border: 3px solid #e5e7eb;
  border-top-color: #1677ff;
  border-radius: 50%;
  animation: spin 0.8s linear infinite;
}

/* === Error State === */
.pay-error-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 12px;
  padding: 60px 24px;
  text-align: center;
  color: #666;
  font-size: 14px;
}

.pay-retry-btn {
  padding: 8px 20px;
  border: 1px solid #1677ff;
  border-radius: 8px;
  background: #fff;
  color: #1677ff;
  font-size: 14px;
  font-weight: 600;
  cursor: pointer;
  transition: all 0.15s;
}

.pay-retry-btn:hover {
  background: #eff6ff;
}

/* === Transition === */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.2s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

/* === Responsive === */
@media (max-width: 479px) {
  .pay-dialog {
    border-radius: 12px;
  }

  .pay-header {
    padding: 16px 20px 0;
  }

  .pay-order-summary {
    margin: 12px 20px;
  }

  .pay-method-section {
    margin: 0 20px 12px;
  }

  .pay-error {
    margin: 0 20px 12px;
  }

  .pay-btn {
    width: calc(100% - 40px);
    margin: 8px 20px 0;
  }

  .pay-hint {
    margin: 8px 20px 16px;
  }

  .pay-pending {
    margin: 0 20px 12px;
  }

  .pay-qr-status {
    margin: 12px 20px 16px;
  }
}
</style>
