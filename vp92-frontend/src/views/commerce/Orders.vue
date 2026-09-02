<template>
  <div class="page" :class="{ active: true }">
    <div class="card">
      <div class="card-header">{{ t('orders.title') }}</div>

      <!-- Loading -->
      <div v-if="loading" class="loading-state">
        <span class="material-symbols-outlined spinning">progress_activity</span>
        <span>{{ t('common.loading') }}</span>
      </div>

      <!-- Empty -->
      <div v-else-if="orders.length === 0" class="empty-state">
        <span class="material-symbols-outlined">receipt_long</span>
        <span>{{ t('orders.messages.noOrders') }}</span>
      </div>

      <!-- Table -->
      <div v-else class="order-table">
        <table>
          <thead>
            <tr>
              <th>{{ t('orders.columns.id') }}</th>
              <th>{{ t('orders.columns.plan') }}</th>
              <th>{{ t('orders.columns.amount') }}</th>
              <th>{{ t('orders.columns.method') }}</th>
              <th>{{ t('orders.columns.status') }}</th>
              <th>{{ t('orders.columns.createdAt') }}</th>
              <th>{{ t('orders.columns.action') }}</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="order in orders" :key="order.id">
              <td class="order-trade-no">{{ order.trade_no }}</td>
              <td>{{ planName(order) }}</td>
              <td class="order-amount">{{ (order.total_amount / 100).toFixed(2) }} {{ t('orders.currency') }}</td>
              <td>{{ paymentMethod(order) }}</td>
              <td>
                <span class="badge" :class="orderStatusClass(order.status)">
                  {{ orderStatusLabel(order.status) }}
                </span>
              </td>
              <td>{{ formatTime(order.created_at) }}</td>
              <td>
                <button
                  v-if="order.status === 0"
                  class="table-btn cancel-btn"
                  @click="cancelOrder(order)"
                >{{ t('common.cancel') }}</button>
                <span v-else class="text-muted">-</span>
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- Toast -->
    <div v-if="toast.show" class="toast" :class="{ show: toast.show }">
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { fetchOrders, cancelOrder as cancelOrderApi } from '@/api/user'
import type { Order } from '@/api/types'

const { t, locale } = useI18n()

const orders = ref<Order[]>([])
const loading = ref(false)
const toast = reactive({ show: false, message: '' })

function showToast(msg: string) {
  toast.message = msg
  toast.show = true
  setTimeout(() => { toast.show = false }, 2500)
}

// Map BCP-47 language tag → locale string for toLocaleDateString
function getDateLocale(): string {
  const m: Record<string, string> = {
    'zh-CN': 'zh-CN',
    'zh-TW': 'zh-TW',
    'en-US': 'en-US',
  }
  return m[locale.value] || 'en-US'
}

function formatTime(ts: number): string {
  if (!ts) return '-'
  return new Date(ts * 1000).toLocaleDateString(getDateLocale(), {
    year: 'numeric', month: '2-digit', day: '2-digit',
    hour: '2-digit', minute: '2-digit',
  })
}

function planName(order: Order): string {
  const name = order.plan?.name || t('plans.badge.unknown')
  const period = periodLabel(order.period)
  return `${name} · ${period}`
}

function periodLabel(period: string): string {
  const map: Record<string, string> = {
    month_price: 'plans.periods.monthly',
    quarter_price: 'plans.periods.quarterly',
    half_year_price: 'plans.periods.halfYear',
    year_price: 'plans.periods.yearly',
    two_year_price: 'plans.periods.twoYear',
    three_year_price: 'plans.periods.threeYear',
    onetime: 'plans.periods.onetime',
    onetime_price: 'plans.periods.onetime',
    reset_traffic: 'plans.periods.resetTraffic',
    reset_price: 'plans.periods.resetTraffic',
    // Aliases (PlanPurchase style)
    monthly: 'plans.periods.monthly',
    quarterly: 'plans.periods.quarterly',
    half_yearly: 'plans.periods.halfYear',
    yearly: 'plans.periods.yearly',
    two_yearly: 'plans.periods.twoYear',
    three_yearly: 'plans.periods.threeYear',
  }
  const key = map[period]
  return key ? t(key) : period
}

function paymentMethod(order: Order): string {
  // Xboard doesn't embed payment method name in order, show based on payment_id
  if (!order.payment_id) return '-'
  return t('payment.online')
}

function orderStatusClass(status: number): string {
  if (status === 3 || status === 4) return 'badge-success'
  if (status === 0) return 'badge-pending'
  return 'badge-cancelled'
}

function orderStatusLabel(status: number): string {
  const map: Record<number, string> = {
    0: 'orders.status.pending',
    1: 'orders.status.processing',
    2: 'orders.status.cancelled',
    3: 'orders.status.completed',
    4: 'orders.status.credited',
  }
  const key = map[status]
  return key ? t(key) : t('orders.status.unknown')
}

async function cancelOrder(order: Order) {
  if (!confirm(t('orders.messages.cancelConfirm', { tradeNo: order.trade_no }))) return
  try {
    const res = await cancelOrderApi(order.trade_no) as any
    if (res?.status === 'success') {
      showToast(t('orders.messages.cancelSuccess'))
      orders.value = orders.value.filter(o => o.id !== order.id)
    } else {
      showToast(res?.message || t('orders.messages.cancelFailed'))
    }
  } catch (e: any) {
    showToast(e.message || t('orders.messages.cancelFailed'))
  }
}

onMounted(async () => {
  loading.value = true
  try {
    const res = (await fetchOrders()) as any
    orders.value = res.data || []
  } catch (e: any) {
    showToast(e.message || t('orders.messages.loadFailed'))
  } finally {
    loading.value = false
  }
})
</script>

<style scoped>
.loading-state,
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 48px 0;
  color: #999;
  font-size: 14px;
}
.loading-state .material-symbols-outlined {
  font-size: 28px;
  color: #0066ff;
}
.spinning {
  animation: spin 1s linear infinite;
}
@keyframes spin {
  to { transform: rotate(360deg); }
}
.empty-state .material-symbols-outlined {
  font-size: 48px;
  color: #d0d5dd;
}
.order-trade-no {
  font-weight: 600;
  color: #0050cb;
}
.order-amount {
  font-weight: 700;
  color: #191c1e;
}
.cancel-btn {
  padding: 4px 12px;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  cursor: pointer;
  background: #fff3f3;
  color: #ba1a1a;
  border: 1px solid #ffdad6;
  transition: all 0.2s;
  font-family: 'Inter', sans-serif;
}
.cancel-btn:hover {
  background: #ba1a1a;
  color: #fff;
}
.text-muted {
  color: #c2c6d8;
  font-size: 12px;
}
</style>
