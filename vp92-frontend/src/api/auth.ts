import client, { setToken } from './client'
import { useAuthStore } from '@/stores/auth'
import type { LoginParams, RegisterParams, ForgotPasswordParams } from './types'

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

export function login(params: LoginParams) {
  return client.post('/passport/auth/login', toFormData(params as any)).then((res: any) => {
    if (res.data?.auth_data) {
      setToken(res.data.auth_data)
      // 后端 login 返回 is_admin 字段
      if (res.data?.is_admin !== undefined) {
        const authStore = useAuthStore()
        authStore.setAdmin(!!res.data.is_admin)
      }
    }
    return res
  })
}

export function register(params: RegisterParams) {
  return client.post('/passport/auth/register', toFormData(params as any)).then((res: any) => {
    if (res.data?.auth_data) {
      setToken(res.data.auth_data)
    }
    return res
  })
}

export function forgotPassword(params: ForgotPasswordParams) {
  return client.post('/passport/auth/forget', toFormData(params as any))
}

export function token2Login(token: string) {
  return client.get('/passport/auth/token2Login', { params: { token } })
}

export function sendEmailVerify(email: string) {
  return client.post('/passport/comm/sendEmailVerify', toFormData({ email }))
}

export function trackInvitePv(code: string) {
  // v38 修复：后端 CommController::pv() 读 $request->input('invite_code')（不是 'code'）
  // 此前发 { code } → 后端 InviteCode::where('code', null)->first() 返 null → pv 永远 0
  // 现象: pbsym pv=0 但邀请码被用 22 次（pv 与 register 不同路径，不影响 invite_user_id 写入）
  return client.post('/passport/comm/pv', toFormData({ invite_code: code }))
}
