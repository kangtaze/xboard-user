import type {
  ApiResponse, PaginatedData, GuestConfig,
  LoginParams, RegisterParams, ForgotPasswordParams,
  UserInfo, SubscribeInfo, UserStat, Plan, Order,
  ServerNode, Ticket, TicketMessage, GiftCardHistory,
  Notice, KnowledgeArticle, PaymentMethod,
} from './types'

// --- Token helpers ---
const TOKEN_KEY = 'fe_auth_token'
const LOCALE_KEY = 'fe_locale'

export function getToken(): string | null {
  return localStorage.getItem(TOKEN_KEY)
}
export function setToken(token: string) {
  localStorage.setItem(TOKEN_KEY, token)
}
export function removeToken() {
  localStorage.removeItem(TOKEN_KEY)
}
export function getSavedLocale(): string {
  return localStorage.getItem(LOCALE_KEY) || ''
}
export function saveLocale(locale: string) {
  localStorage.setItem(LOCALE_KEY, locale)
}

// --- Axios Instance ---
import axios from 'axios'

const client = axios.create({
  baseURL: '/api/v1',
  timeout: 12000,
  headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
})

// Request interceptor
client.interceptors.request.use((config) => {
  const token = getToken()
  if (token) {
    // token 已含 "Bearer " 前缀，直接使用
    config.headers.Authorization = token
  }
  // GET 请求加 cache-busting
  if (config.method === 'get') {
    config.params = { ...config.params, t: Date.now() }
  }
  const locale = getSavedLocale()
  if (locale) {
    config.headers['Accept-Language'] = locale
  }
  return config
})

// Response interceptor — 对齐 Xboard 原版: 只解包一层
client.interceptors.response.use(
  (response) => {
    // 只解一层: 返回 JSON body { status, message, data, error }
    return response.data
  },
  (error) => {
    const status = error.response?.status
    const body = error.response?.data || { message: '未知错误' }
    const message = body.message || '未知异常'
    const errors = body.errors

    // 401: token 失效/未授权 → 清 token + 带 redirect 参数跳登录页
    // 保留当前完整路径（含 query, 如 ?checkout_plan=X），让用户重新登录后能自动跳回
    if (status === 401) {
      removeToken()
      const currentPath = window.location.pathname + window.location.search
      if (!currentPath.startsWith('/auth/')) {
        const redirect = encodeURIComponent(currentPath)
        // 用 location.assign 强制刷新,确保 token/storage 状态彻底重置
        window.location.assign(`/auth/login?redirect=${redirect}`)
      }
      return Promise.resolve({ code: status, message: message || '登录已过期', errors })
    }

    // 其他错误: reject 让 try/catch 正常捕获
    const apiErr = new Error(message)
    ;(apiErr as any).code = status || -1
    ;(apiErr as any).errors = errors
    return Promise.reject(apiErr)
  },
)

export default client
