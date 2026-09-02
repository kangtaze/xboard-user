/**
 * 用途: 邀请码 invite_code 跨页面持久化（sessionStorage）
 * 调用方: RegisterPage.vue（读取/清除）、PlanPurchase.vue（清除）、landing.blade.php（间接通过 SPA 注册流程）
 * 被调用方: window.sessionStorage（key = vp92_invite_code）
 * 关联记录: 2026-09-02 invite_code 跨页面保持 Bug 修复（方案 B Step 1）
 */

const INVITE_KEY = 'vp92_invite_code'
const MAX_LENGTH = 64
const VALID_RE = /^[A-Za-z0-9_-]+$/

export function setInviteCode(code: string | undefined | null): void {
  if (!code || typeof code !== 'string') return
  const trimmed = code.trim()
  if (!trimmed) return
  if (trimmed.length > MAX_LENGTH) return
  if (!VALID_RE.test(trimmed)) return
  try {
    sessionStorage.setItem(INVITE_KEY, trimmed)
  } catch {
    // sessionStorage 不可用（隐私模式等）静默失败
  }
}

export function getInviteCode(): string {
  try {
    const v = sessionStorage.getItem(INVITE_KEY)
    if (!v) return ''
    // 读出时再次验证（防外部篡改）
    if (!VALID_RE.test(v)) {
      sessionStorage.removeItem(INVITE_KEY)
      return ''
    }
    return v
  } catch {
    return ''
  }
}

export function clearInviteCode(): void {
  try {
    sessionStorage.removeItem(INVITE_KEY)
  } catch {
    // 静默
  }
}

export function isInviteValid(code: string): boolean {
  if (!code || typeof code !== 'string') return false
  return VALID_RE.test(code) && code.length <= MAX_LENGTH
}
