<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useRoute } from 'vue-router'
import { fetchPlans, createOrder, getPaymentMethods, cancelOrder, fetchOrders, getOrderDetail } from '@/api/user'
import CheckoutDialog from '@/components/common/CheckoutDialog.vue'
import Toast from '@/components/common/Toast.vue'
import type { Plan } from '@/api/types'

const route = useRoute()
const toastRef = ref<InstanceType<typeof import('@/components/common/Toast.vue').default> | null>(null)

const plans = ref<Plan[]>([])
const loading = ref(true)

// Checkout dialog state
const showCheckout = ref(false)
const selectedPlan = ref<Plan | null>(null)
const selectedPeriod = ref('month_price')
const checkoutTradeNo = ref('')
const checkoutOrderAmount = ref<number>(0)
const checkoutOrderPeriod = ref('')

// Confirm dialog state → moved into CheckoutDialog
const pendingConflict = ref(false)

// --- Price formatting ---
function formatPrice(cents: number): string {
  if (!cents && cents !== 0) return '0'
  const val = cents / 100
  return val % 1 === 0 ? val.toFixed(0) : val.toFixed(2)
}

// --- Period label ---
function getPeriodLabel(period: string): string {
  switch (period) {
    case 'month_price': return '/月'
    case 'quarter_price': return '/季'
    case 'half_year_price': return '/半年'
    case 'year_price': return '/年'
    case 'two_year_price': return '/2年'
    case 'three_year_price': return '/3年'
    default: return '/月'
  }
}

const selectedPeriodFor = ref<Record<number, string>>({})

// --- Cycle alias mapping (landing page may send short names like 'monthly') ---
const CYCLE_ALIAS_MAP: Record<string, string> = {
  monthly: 'month_price',
  quarterly: 'quarter_price',
  half_yearly: 'half_year_price',
  yearly: 'year_price',
  two_yearly: 'two_year_price',
  three_yearly: 'three_year_price',
}
function resolvePeriod(alias: string | null | undefined): string {
  if (!alias) return 'month_price'
  return CYCLE_ALIAS_MAP[alias] || alias
}

// --- Get all available periods for a plan ---
function getPlanPeriods(plan: Plan): { period: string; amount: number; label: string }[] {
  const items: { period: string; amount: number; label: string }[] = []
  if (plan.month_price && plan.month_price > 0) items.push({ period: 'month_price', amount: plan.month_price, label: '月付' })
  if (plan.quarter_price && plan.quarter_price > 0) items.push({ period: 'quarter_price', amount: plan.quarter_price, label: '季付' })
  if (plan.half_year_price && plan.half_year_price > 0) items.push({ period: 'half_year_price', amount: plan.half_year_price, label: '半年付' })
  if (plan.year_price && plan.year_price > 0) items.push({ period: 'year_price', amount: plan.year_price, label: '年付' })
  if (plan.two_year_price && plan.two_year_price > 0) items.push({ period: 'two_year_price', amount: plan.two_year_price, label: '2年付' })
  if (plan.three_year_price && plan.three_year_price > 0) items.push({ period: 'three_year_price', amount: plan.three_year_price, label: '3年付' })
  return items
}

// --- Get selected price for display (respects period selector) ---
function getSelectedPrice(plan: Plan): { amount: number; period: string; label: string } {
  const sel = selectedPeriodFor.value[plan.id]
  if (sel) {
    const p = getPlanPeriods(plan).find(p => p.period === sel)
    if (p) return p
  }
  return getPlanPrice(plan)
}

// --- Get available price for a plan ---
function getPlanPrice(plan: Plan): { amount: number; period: string; label: string } {
  // Prefer monthly price as default display
  if (plan.month_price && plan.month_price > 0) {
    return { amount: plan.month_price, period: 'month_price', label: getPeriodLabel('month_price') }
  }
  if (plan.quarter_price && plan.quarter_price > 0) {
    return { amount: plan.quarter_price, period: 'quarter_price', label: getPeriodLabel('quarter_price') }
  }
  if (plan.year_price && plan.year_price > 0) {
    return { amount: plan.year_price, period: 'year_price', label: getPeriodLabel('year_price') }
  }
  return { amount: 0, period: 'month_price', label: '/月' }
}

// --- Parse plan features from content ---
function parsePlanFeatures(plan: Plan): string[] {
  if (!plan.content) {
    // Generate features from transfer_enable and other fields
    const features: string[] = []
    if (plan.transfer_enable) {
      features.push(`${plan.transfer_enable}GB 每月流量`)
    }
    features.push('100+ 国家线路')
    features.push('AI 工具解锁')
    features.push('8K 流媒体')
    if (plan.speed_limit) {
      features.push(`${plan.speed_limit}Mbps 速度`)
    }
    if (plan.device_limit) {
      features.push(`${plan.device_limit} 台设备`)
    } else {
      features.push('无限设备')
    }
    return features
  }

  // Content is markdown; extract bullet points or use line breaks, deduplicate
  const lines = plan.content.split('\n').filter(l => l.trim().length > 0)
  const unique: string[] = []
  const seen = new Set<string>()
  for (const line of lines) {
    const clean = line.replace(/^[-*]\s*/, '').replace(/\*\*/g, '').trim()
    if (clean && !seen.has(clean)) {
      seen.add(clean)
      unique.push(clean)
    }
  }
  return unique
}

// --- Determine plan badge ---
function getPlanBadge(plan: Plan): string {
  // Sort order in grid: plan with highest transfer_enable goes to middle (recommended)
  return plan.name || '基础版'
}

// --- Determine if recommended ---
function isRecommended(plan: Plan, allPlans: Plan[]): boolean {
  if (allPlans.length < 3) return false
  // Sort by transfer_enable descending; the middle one is recommended
  const sorted = [...allPlans].sort((a, b) => (b.transfer_enable || 0) - (a.transfer_enable || 0))
  return sorted[1]?.id === plan.id
}

// --- Sorted plans for display ---
const displayPlans = computed(() => {
  if (plans.value.length === 0) return []
  // Sort: VIP1 (lowest) -> VIP3 (highest, middle) -> VIP2
  // This puts the recommended plan in the center
  const sorted = [...plans.value].sort((a, b) => (a.transfer_enable || 0) - (b.transfer_enable || 0))
  if (sorted.length === 3) {
    // Reorder: [smallest, largest, middle] for VIP1, VIP3(recommended), VIP2 layout
    return [sorted[0], sorted[2], sorted[1]]
  }
  return sorted
})

// --- Create order and open checkout ---
async function handleSelectPlan(plan: Plan) {
  selectedPlan.value = plan
  const price = getSelectedPrice(plan)
  selectedPeriod.value = price.period

  try {
    const res = await createOrder({
      plan_id: plan.id,
      period: price.period,
    }) as any
    // API returns trade_no directly as string in data field
    const tradeNo = typeof res?.data === 'string' ? res.data : res?.data?.trade_no
    if (tradeNo) {
      checkoutTradeNo.value = tradeNo
      // 获取订单实际金额和周期
      try {
        const detail = await getOrderDetail(tradeNo) as any
        const order = detail?.data?.data ?? detail?.data
        checkoutOrderAmount.value = order?.total_amount ?? price.amount
        checkoutOrderPeriod.value = order?.period ?? price.period
      } catch {
        // 获取失败时使用前端计算的值
        checkoutOrderAmount.value = price.amount
        checkoutOrderPeriod.value = price.period
      }
      showCheckout.value = true
    }
  } catch (e: any) {
    const errMsg = e?.message || e?.toString?.() || ''
    const isPendingBlock = /unpaid|pending|未付款|未支付|待支付|开通中|已有.*订单/i.test(errMsg)
    if (isPendingBlock) {
      // Open checkout with pending conflict flag — CheckoutDialog handles it
      pendingConflict.value = true
      checkoutTradeNo.value = '' // no new trade_no yet
      selectedPlan.value = plan
      checkoutOrderAmount.value = 0
      checkoutOrderPeriod.value = ''
      showCheckout.value = true
    } else {
      console.error('Create order failed:', e)
      toastRef.value?.show(typeof e === 'string' ? e : (e?.message || '操作失败，请稍后重试'))
    }
  }
}

// --- Checkout events ---
function handleCheckoutSuccess() {
  showCheckout.value = false
  pendingConflict.value = false
  loadPlans()
}

function handleCheckoutCancelOld() {
  // User cancelled old order from CheckoutDialog, now create new one
  pendingConflict.value = false
  if (selectedPlan.value) {
    handleSelectPlan(selectedPlan.value)
  }
}

// --- Load plans ---
async function loadPlans() {
  try {
    const res = await fetchPlans() as any
    const data = res?.data
    if (Array.isArray(data)) {
      plans.value = data.filter((p: Plan) => p.show !== false && p.show !== 0)
      // Default select first period for each plan
      for (const plan of plans.value) {
        const periods = getPlanPeriods(plan)
        if (periods.length > 0 && !selectedPeriodFor.value[plan.id]) {
          selectedPeriodFor.value[plan.id] = periods[0].period
        }
      }
    } else if (Array.isArray(data?.data)) {
      plans.value = data.data.filter((p: Plan) => p.show !== false && p.show !== 0)
    }
  } catch (e) {
    console.error('Failed to fetch plans:', e)
  }
}

onMounted(async () => {
  loading.value = true
  await loadPlans()
  loading.value = false
  // Auto-checkout if arriving with ?checkout_plan=ID&checkout_cycle=PERIOD (from landing page)
  const checkoutPlanId = parseInt((route.query.checkout_plan as string) || '0', 10)
  const checkoutCycle = resolvePeriod(route.query.checkout_cycle as string)
  if (checkoutPlanId > 0 && plans.value.length > 0) {
    const target = plans.value.find(p => p.id === checkoutPlanId)
    if (target) {
      const periods = getPlanPeriods(target)
      const validPeriod = periods.find(p => p.period === checkoutCycle)
      selectedPeriodFor.value[target.id] = validPeriod ? validPeriod.period : (periods[0]?.period || 'month_price')
      await handleSelectPlan(target)
      // 清理 query param 防止刷新重复触发
      try {
        const cleanQuery = { ...route.query }
        delete cleanQuery.checkout_plan
        delete cleanQuery.checkout_cycle
        await route.replace({ query: cleanQuery })
      } catch { /* ignore */ }
    }
  }
})
</script>

<template>
  <div class="plan-purchase-page">
    <!-- Loading -->
    <div v-if="loading" class="loading-state">
      <span class="vp-spinner vp-spinner-md"></span>
      <span>加载套餐...</span>
    </div>

    <!-- Plans Grid -->
    <div v-else-if="displayPlans.length > 0" class="plans-grid">
      <div
        v-for="plan in displayPlans"
        :key="plan.id"
        class="plan-card"
        :class="{ recommended: isRecommended(plan, plans) }"
      >
        <img class="plan-card-img" :src="'/vip-banners/' + plan.name + '.png'" :alt="plan.name" />
        <div class="plan-card-features">
          <div v-for="(feat, i) in parsePlanFeatures(plan)" :key="i" class="plan-feat">
            <span class="material-symbols-outlined" style="font-size: 18px;">check_circle</span>
            {{ feat }}
          </div>
        </div>
        <!-- Period selector -->
        <div class="plan-period-selector">
          <div class="period-price-main">
            <span class="period-currency">&yen;</span>
            <span class="period-amount">{{ formatPrice(getSelectedPrice(plan).amount) }}</span>
          </div>
          <div class="period-options">
            <button
              v-for="p in getPlanPeriods(plan)"
              :key="p.period"
              class="period-chip"
              :class="{ active: selectedPeriodFor[plan.id] === p.period }"
              @click="selectedPeriodFor[plan.id] = p.period"
            >
              <span class="period-chip-label">{{ p.label }}</span>
              <span class="period-chip-price">&yen;{{ formatPrice(p.amount) }}</span>
            </button>
          </div>
        </div>
        <button
          class="plan-card-btn"
          :class="{ outline: !isRecommended(plan, plans) }"
          @click="handleSelectPlan(plan)"
        >
          {{ isRecommended(plan, plans) ? '立即订阅' : '选择此方案' }}
        </button>
      </div>
    </div>

    <!-- Empty State -->
    <div v-else class="empty-state">
      <span class="material-symbols-outlined" style="font-size: 48px; color: #c2c6d8;">shopping_cart</span>
      <p>暂无可用套餐</p>
    </div>

    <!-- Checkout Dialog -->
    <CheckoutDialog
      v-if="showCheckout"
      :visible="showCheckout"
      :trade-no="checkoutTradeNo"
      :pending-conflict="pendingConflict"
      :selected-plan="selectedPlan"
      :order-amount="checkoutOrderAmount"
      :order-period="checkoutOrderPeriod"
      @close="showCheckout = false; pendingConflict = false"
      @success="handleCheckoutSuccess"
      @cancel-old="handleCheckoutCancelOld"
    />
    <Toast ref="toastRef" />
  </div>
</template>

<style scoped>
.plan-purchase-page {
  padding: 0;
}

/* === Loading === */
.loading-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 80px 0;
  color: #999;
  font-size: 14px;
}

/* === Empty State === */
.empty-state {
  display: flex;
  flex-direction: column;
  align-items: center;
  gap: 12px;
  padding: 80px 0;
  color: #999;
  font-size: 14px;
}

/* === Plans Grid === */
.plans-grid {
  display: grid;
  grid-template-columns: repeat(3, 1fr);
  gap: 20px;
}

/* === Plan Card === */
.plan-card {
  background: #fff;
  border-radius: 16px;
  padding: 32px 24px;
  border: 1.5px solid #e0e3e5;
  display: flex;
  flex-direction: column;
  align-items: center;
  position: relative;
  transition: all 0.25s;
}

.plan-card:hover {
  transform: translateY(-4px);
  box-shadow: 0 12px 32px rgba(0, 0, 0, 0.08);
}

.plan-card.recommended {
  border-color: #0066ff;
  box-shadow: 0 0 0 1px #0066ff;
}

.plan-card-badge {
  padding: 4px 16px;
  background: #f0f4ff;
  color: #0050cb;
  border-radius: 6px;
  font-size: 12px;
  font-weight: 600;
  margin-bottom: 8px;
}

.plan-card-badge.hot {
  background: #0066ff;
  color: #fff;
}

/* === Plan Card Image === */
.plan-card-img {
  width: 100%;
  border-radius: 12px;
  margin-bottom: 16px;
  object-fit: cover;
}

/* === Period Selector (Premium Design) === */
.plan-period-selector {
  margin-bottom: 20px;
  text-align: center;
  width: 100%;
}

.period-price-main {
  margin-bottom: 12px;
  display: flex;
  align-items: baseline;
  justify-content: center;
  gap: 2px;
}

.period-currency {
  font-size: 20px;
  font-weight: 700;
  color: #191c1e;
}

.period-amount {
  font-size: 40px;
  font-weight: 900;
  color: #191c1e;
  letter-spacing: -1px;
  line-height: 1;
}

.period-options {
  display: flex;
  flex-wrap: wrap;
  gap: 8px;
  justify-content: center;
}

.period-chip {
  display: flex;
  flex-direction: column;
  align-items: center;
  padding: 8px 16px 6px;
  border-radius: 12px;
  border: 1.5px solid #e2e8f0;
  background: #fff;
  cursor: pointer;
  transition: all 0.25s cubic-bezier(.34,1.56,.64,1);
  min-width: 72px;
}

.period-chip:hover {
  border-color: #a0c4ff;
  background: #f8faff;
  transform: translateY(-1px);
}

.period-chip.active {
  border-color: transparent;
  background: linear-gradient(135deg, #0066ff 0%, #0052cc 100%);
  box-shadow: 0 4px 14px rgba(0, 102, 255, 0.3);
  transform: translateY(-1px);
}

.period-chip-label {
  font-size: 11px;
  font-weight: 600;
  color: #64748b;
  margin-bottom: 2px;
}

.period-chip.active .period-chip-label {
  color: rgba(255, 255, 255, 0.8);
}

.period-chip-price {
  font-size: 15px;
  font-weight: 800;
  color: #191c1e;
}

.period-chip.active .period-chip-price {
  color: #fff;
}

/* === Features === */
.plan-card-features {
  width: 100%;
  margin-bottom: 24px;
}

.plan-feat {
  display: flex;
  align-items: center;
  gap: 10px;
  padding: 8px 0;
  font-size: 14px;
  color: #424656;
  border-bottom: 1px solid #f0f2f4;
}

.plan-feat:last-child {
  border-bottom: none;
}

.plan-feat .material-symbols-outlined {
  color: #22c55e;
}

/* === CTA Button === */
.plan-card-btn {
  width: 100%;
  padding: 14px;
  border-radius: 12px;
  font-size: 14px;
  font-weight: 700;
  cursor: pointer;
  font-family: 'Inter', sans-serif;
  transition: all 0.2s;
  background: #0066ff;
  color: #fff;
  border: none;
}

.plan-card-btn:hover {
  background: #0050cb;
}

.plan-card-btn.outline {
  background: #fff;
  color: #191c1e;
  border: 1.5px solid #e0e3e5;
}

.plan-card-btn.outline:hover {
  border-color: #0066ff;
  color: #0066ff;
  background: #f0f7ff;
}

/* === Responsive === */
@media (max-width: 1024px) {
  .plans-grid {
    grid-template-columns: 1fr;
    max-width: 420px;
    margin: 0 auto;
  }
}

@media (max-width: 768px) {
  .plan-card {
    padding: 24px 20px;
  }

  .period-amount {
    font-size: 32px;
  }

  .period-chip {
    padding: 6px 12px 4px;
    min-width: 64px;
  }

  .period-chip-price {
    font-size: 13px;
  }
}
</style>
