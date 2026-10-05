// ========================================
// Ferrari — TypeScript API Types
// 所有类型严格对齐 Xboard 后端实际返回
// ========================================

// --- Response Wrapper ---
export interface ApiResponse<T> {
  status: string
  data: T
  message: string
  error: any
}

export interface PaginatedData<T> {
  data: T[]
  pagination: {
    current_page: number
    last_page: number
    per_page: number
    total: number
  }
}

// --- Guest Config ---
export interface GuestConfig {
  is_email_verify: boolean
  is_recaptcha: boolean
  recaptcha_site_key: string
  is_invite_force: boolean
  logo_url: string
  currency: string
  tos_url: string | null
  is_captcha: boolean
  app_description: string
  app_url: string
  logo: string | null
}

// --- Auth ---
export interface LoginParams {
  email: string
  password: string
}

export interface RegisterParams {
  email: string
  password: string
  password_confirm: string
  invite_code?: string
  email_code?: string
  recaptcha_data?: string
}

export interface ForgotPasswordParams {
  email: string
  email_code: string
  new_password: string
}

export interface AuthData {
  token: string
  auth_data: string
  is_admin: boolean
}

// --- User ---
// GET /api/v1/user/info 实际返回
export interface UserInfo {
  email: string
  uuid: string
  avatar_url: string
  transfer_enable: number
  last_login_at: number
  created_at: number
  banned: boolean
  remind_expire: boolean
  remind_traffic: boolean
  expired_at: number
  balance: number              // 分/cents
  commission_balance: number   // 分/cents
  plan_id: number | null
  group_id: number | null      // v61 套餐组 ID (VIP1=2 / VIP2=3 / VIP3=4)
  group?: {                    // v61 套餐组对象,group_id=5 (admin 试用) 时后端 strip 字段
    id: number
    name: string
  } | null
  discount: number | null
  commission_rate: number | null
  telegram_id: number | null
}

// GET /api/v1/user/getStat 实际返回 [pending_orders, pending_tickets, invitee_count]
export type UserStat = [number, number, number]

export interface SubscribeInfo {
  plan_id: number
  token: string
  subscribe_url: string
  reset_day: number
  expired_at: number         // 后端用 expired_at
  next_reset_at: number | null
  u: number                 // 已用上传 (bytes)
  d: number                 // 已用下载 (bytes)
  transfer_enable: number   // 总流量 (bytes)
  device_limit: number | null
  speed_limit: number | null
  plan?: any                // 嵌套的 plan 对象 { id, name, transfer_enable(GB), ... }
  email?: string
  uuid?: string
}

// --- Plan ---
export interface PlanPrice {
  monthly: number
  quarterly: number
  half_year: number
  yearly: number
  onetime: number
}

export interface Plan {
  id: number
  name: string
  group_id?: number
  tags?: string[]
  content: string            // markdown 字符串
  transfer_enable: number    // GB 单位 (600 = 600GB)
  speed_limit: number | null
  device_limit: number | null
  sort: number
  show: number | boolean
  sell?: boolean
  renew?: boolean
  created_at: number
  updated_at: number
  month_price: number         // 分/cents (3000 = ¥30)
  quarter_price: number
  half_year_price: number
  year_price: number
  two_year_price?: number
  three_year_price?: number
  onetime_price?: number
  reset_price?: number
  capacity_limit?: number | string | null
  reset_traffic_method?: number | null
  prices?: { [key: string]: string }  // 原始 prices JSON
}

// --- Order ---
// GET /api/v1/user/order/fetch 实际返回
export interface Order {
  id: number
  trade_no: string
  user_id: number
  plan_id: number
  coupon_id: number | null
  payment_id: number | null
  type: number                // 1=新购 2=续费 3=变更
  period: string              // "month_price", "year_price" 等
  callback_no: string | null
  total_amount: number        // 分/cents
  handling_amount: number | null
  discount_amount: number | null
  surplus_amount: number | null
  surplus_credit: number | null
  balance_amount: number | null
  surplus_order_ids: string | null
  status: number              // 0=待支付 1=开通中 2=已取消 3=已完成 4=已折抵
  commission_status: number
  commission_balance: number
  actual_commission_balance: number | null
  paid_at: number | null
  created_at: number
  updated_at: number
  plan: Plan                  // 嵌套的 plan 对象
}

// --- Server/Node ---
export interface ServerNode {
  id: number
  name: string
  tags: string[]
  rate: number
  host: string
  port: number
  server_port: number
  type: string
  group_id: number
  country_code: string
  created_at: number
  updated_at: number
}

// --- Ticket ---
export interface Ticket {
  id: number
  user_id: number
  status: number
  subject: string
  level: number
  is_reply: number
  created_at: number
  updated_at: number
  last_reply_at: number | null
  messages?: TicketMessage[]
}

export interface TicketMessage {
  id: number
  ticket_id: number
  user_id: number
  is_me: boolean
  message: string
  created_at: number
  updated_at: number
}

// --- Gift Card ---
// 后端 /user/gift-card/check 返回结构
export interface GiftCardCheckResponse {
  code_info: {
    code: string
    template: {
      name: string
      description: string | null
      type: number
      type_name: string
      icon: string | null
      background_image: string | null
      theme_color: string
    }
    status: number
    status_name: string
    expires_at: number | null
    usage_count: number
    max_usage: number
    plan_info?: any
  }
  reward_preview: RewardPreview
  can_redeem: boolean
  reason: string | null
}

export interface RewardPreview {
  balance?: number          // 余额（分/cents）
  transfer_enable?: number  // 流量（bytes）
  expire_days?: number      // 到期天数
  reset_package?: boolean   // 重置流量包
  plan_id?: number          // 套餐 ID
  plan_validity_days?: number // 套餐有效天数
  device_limit?: number     // 设备限制数量
  invite_reward_rate?: number
}

// 后端 /user/gift-card/history 返回的单条记录
export interface GiftCardHistory {
  id: number
  code: string                    // 脱敏后的兑换码
  template_name: string
  template_type: number
  template_type_name: string
  rewards_given: RewardPreview    // 实际发放的奖励
  invite_rewards: RewardPreview | null
  multiplier_applied: number
  created_at: number
}

// 后端 /user/gift-card/types 返回的类型映射
export type GiftCardTypeMap = Record<string, string>

// --- Invite ---
// GET /api/v1/user/invite/fetch 实际返回
export interface InviteResponse {
  codes: InviteCode[]
  // Backend stat: [registered_count, confirmed_commission(cents), pending_commission(cents), commission_rate(%), available_balance(cents)]
  stat: InviteStat
}

export type InviteStat = [number, number, number, number, number]

export interface InviteCode {
  user_id: number
  code: string
  pv: number
  status: boolean
  created_at: number
  updated_at: number
}

// GET /api/v1/user/invite/details 返回的佣金记录
export interface CommissionLogItem {
  id: number
  email: string           // 购买者邮箱
  trade_no: string        // 订单号
  order_amount: number    // 订单金额(分)
  get_amount: number      // 获得佣金(分)
  created_at: number      // 时间戳
}

// --- Notice ---
export interface Notice {
  id: number
  title: string
  content: string
  img_url: string
  show: number
  created_at: number
  updated_at: number
}

// --- Knowledge ---
export interface KnowledgeArticle {
  id: number
  category_id: number
  title: string
  body: string
  show: number
  sort: number
  created_at: number
  updated_at: number
}

export interface KnowledgeCategory {
  id: number
  name: string
  language: string
  created_at: number
  updated_at: number
}

// --- Payment ---
export interface PaymentMethod {
  icon: string
  id: number
  name: string
  handling_fee_percent: number
}

// --- Telegram ---
export interface TelegramBotInfo {
  token?: string
  username?: string
}
