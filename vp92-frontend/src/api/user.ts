import client from './client'
import type {
  UserInfo, SubscribeInfo, UserStat, Plan, Order,
  ServerNode, Ticket, TicketMessage, GiftCardHistory,
  Notice, KnowledgeArticle, KnowledgeCategory,
  PaymentMethod, GuestConfig,
} from './types'

/** 将对象转为 URLSearchParams */
function toFormData(obj: Record<string, any>): URLSearchParams {
  const params = new URLSearchParams()
  for (const [k, v] of Object.entries(obj)) {
    if (v !== undefined && v !== null) {
      params.append(k, String(v))
    }
  }
  return params
}

// --- Guest ---
export function getGuestConfig() {
  return client.get('/guest/comm/config')
}

export function getGuestPlans() {
  return client.get('/guest/plan/fetch')
}

// --- User ---
export function getUserInfo() {
  return client.get('/user/info')
}

export function getUserStat() {
  return client.get('/user/getStat')
}

export function checkLogin() {
  return client.get('/user/checkLogin')
}

export function changePassword(params: { old_password: string; new_password: string }) {
  return client.post('/user/changePassword', toFormData(params))
}

export function updateUser(params: { remind_expire?: number; remind_traffic?: number }) {
  return client.post('/user/update', toFormData(params))
}

export function resetSecurity() {
  return client.get('/user/resetSecurity')
}

export function getSubscribe() {
  return client.get('/user/getSubscribe')
}

export function getActiveSession() {
  return client.get('/user/getActiveSession')
}

export function removeActiveSession(params: { session_id: string }) {
  return client.post('/user/removeActiveSession', toFormData(params))
}

// --- Plans ---
// 优先用用户端点（保留未来 user-specific 字段扩展）；401/403 → 自动 fallback 到 guest 端点
// 防止 token 未同步/失效导致 PlanPurchase 静默空白
export async function fetchPlans() {
  try {
    return await client.get('/user/plan/fetch')
  } catch (e: any) {
    const status = e?.code
    if (status === 401 || status === 403) {
      console.warn('[fetchPlans] user endpoint failed, falling back to guest:', status)
      return await client.get('/guest/plan/fetch')
    }
    throw e
  }
}

// --- Orders ---
export function createOrder(params: { plan_id: number; period: string; coupon_code?: string }) {
  return client.post('/user/order/save', toFormData(params))
}

export function checkoutOrder(params: { trade_no: string; method: string; token?: string }) {
  return client.post('/user/order/checkout', toFormData(params))
}

export function checkOrder(tradeNo: string) {
  return client.get('/user/order/check', { params: { trade_no: tradeNo } })
}

export function getOrderDetail(tradeNo: string) {
  return client.get('/user/order/detail', { params: { trade_no: tradeNo } })
}

export function fetchOrders(params?: { status?: number }) {
  return client.get('/user/order/fetch', { params })
}

export function getPaymentMethods() {
  return client.get('/user/order/getPaymentMethod')
}

export function cancelOrder(tradeNo: string) {
  return client.post('/user/order/cancel', toFormData({ trade_no: tradeNo }))
}

// --- Servers ---
export function fetchServers() {
  return client.get('/user/server/fetch')
}

export function fetchEdgetunnelNodes() {
  return client.get('/user/server/edgetunnel')
}

// --- Tickets ---
export function fetchTickets(params?: { id?: number }) {
  return client.get('/user/ticket/fetch', { params })
}

export function createTicket(params: { subject: string; level: number; message: string }) {
  return client.post('/user/ticket/save', toFormData(params))
}

export function replyTicket(params: { id: number; message: string }) {
  return client.post('/user/ticket/reply', toFormData(params))
}

export function closeTicket(id: number) {
  return client.post('/user/ticket/close', toFormData({ id }))
}

// --- Gift Cards ---
export function checkGiftCard(code: string) {
  return client.post('/user/gift-card/check', toFormData({ code }))
}

export function redeemGiftCard(code: string) {
  return client.post('/user/gift-card/redeem', toFormData({ code }))
}

export function getGiftCardHistory() {
  return client.get('/user/gift-card/history')
}

export function getGiftCardDetail(id: number) {
  return client.get('/user/gift-card/detail', { params: { id } })
}

export function getGiftCardTypes() {
  return client.get('/user/gift-card/types')
}

// --- Invite ---
export function getInviteSummary() {
  return client.get('/user/invite/fetch')
}

export function generateInviteCode() {
  return client.get('/user/invite/save')
}

export function getInviteDetails() {
  return client.get('/user/invite/details')
}

// --- Notices ---
export function fetchNotices(params?: { current?: number }) {
  return client.get('/user/notice/fetch', { params })
}

// --- Knowledge ---
export function fetchKnowledge(params?: { id?: number; language?: string; keyword?: string }) {
  return client.get('/user/knowledge/fetch', { params })
}

export function getKnowledgeCategories() {
  return client.get('/user/knowledge/getCategory')
}

// --- App Download ---
export function getAppVersion() {
  return client.get('/user/app/getVersion')
}

// --- Traffic ---
export function getTrafficLog() {
  return client.get('/user/stat/getTrafficLog')
}

// --- Coupon ---
export function checkCoupon(params: { code: string; plan_id?: number; period?: string }) {
  return client.post('/user/coupon/check', toFormData(params))
}