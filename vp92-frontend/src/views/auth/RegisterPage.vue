<template>
  <div class="login-layout">
    <!-- Left: 3D Globe -->
    <div class="globe-side">
      <div class="globe-brand">
        <img v-if="configStore.guestConfig.logo" :src="configStore.guestConfig.logo" class="globe-brand-logo" alt="Logo"/>
        <div v-else class="globe-brand-logo"><span>VP</span></div>
        <div>
          <div class="globe-brand-name">VP92 极速加速器</div>
          <div class="globe-brand-sub">Stable &middot; Fast &middot; Secure</div>
        </div>
      </div>
      <div ref="globeEl" class="globe-canvas"></div>
      <div class="globe-overlay"></div>
    </div>

    <!-- Right: Register Form -->
    <div class="form-side">
      <div class="register-card">
        <img v-if="configStore.guestConfig.logo" :src="configStore.guestConfig.logo" class="logo" alt="Logo"/>
        <div v-else class="logo"><span>VP</span></div>
        <h1>创建账户</h1>
        <p class="subtitle">开始使用 VP92 极速加速</p>

        <form @submit.prevent="handleRegister">
          <div class="input-group">
            <label>邮箱地址</label>
            <input v-model="form.email" type="email" placeholder="请输入邮箱" required autocomplete="email" />
          </div>
          <div class="input-group">
            <label>密码</label>
            <input v-model="form.password" type="password" placeholder="至少 8 位字符" required minlength="8" autocomplete="new-password" />
          </div>
          <div class="input-group">
            <label>确认密码</label>
            <input v-model="form.passwordConfirm" type="password" placeholder="再次输入密码" required autocomplete="new-password" />
          </div>
          <div class="input-group">
            <label>邀请码 <span class="optional">（选填）</span></label>
            <input v-model="form.inviteCode" type="text" :placeholder="t('auth.register.inviteCodePlaceholder')" :readonly="isInviteLocked" />
          </div>
          <div class="input-group">
            <label>邮箱验证码</label>
            <div class="input-group-with-btn">
              <input v-model="form.emailCode" type="text" placeholder="输入验证码" required maxlength="6" />
              <button type="button" class="code-btn" :disabled="codeCooldown > 0" @click="handleSendCode">
                {{ codeCooldown > 0 ? `${codeCooldown}s 后重试` : '发送验证码' }}
              </button>
            </div>
            <p class="email-tip">
              <span class="material-symbols-outlined" style="font-size:14px;vertical-align:-2px;">info</span>
              验证码邮件可能被归入垃圾箱，请检查垃圾箱/广告邮件夹（QQ邮箱需特别注意）
            </p>
          </div>
          <div class="agree-row">
            <input v-model="form.agree" type="checkbox" required id="agree" />
            <label for="agree">我已阅读并同意 <a href="javascript:void(0)">用户协议</a> 和 <a href="javascript:void(0)">隐私政策</a></label>
          </div>
          <button type="submit" class="register-btn" :disabled="loading">
            <span v-if="loading" class="vp-spinner vp-spinner-sm"></span>
            <span v-else>注 册</span>
          </button>
        </form>

        <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

        <div class="divider"><span>或者</span></div>
        <div class="register">
          已有账户？<router-link :to="{ name: 'login', query: route.query }">立即登录</router-link>
        </div>
      </div>
    </div>

    <Teleport to="body">
      <div class="toast" :class="{ show: toastVisible }">{{ toastMsg }}</div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { register, sendEmailVerify, trackInvitePv } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { useConfigStore } from '@/stores/config'
import { getInviteCode, setInviteCode, clearInviteCode } from '@/utils/invite'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const configStore = useConfigStore()
const { t } = useI18n()

const form = reactive({
  email: '',
  password: '',
  passwordConfirm: '',
  inviteCode: (route.query.code as string) || getInviteCode(),
  emailCode: '',
  agree: false,
})
const loading = ref(false)
const errorMsg = ref('')
const codeCooldown = ref(0)
const toastMsg = ref('')
const toastVisible = ref(false)
const globeEl = ref<HTMLElement | null>(null)
const isInviteLocked = computed(() => !!(route.query.code || getInviteCode()))

function showToast(msg: string) {
  toastMsg.value = msg
  toastVisible.value = true
  setTimeout(() => { toastVisible.value = false }, 2500)
}

async function handleSendCode() {
  if (!form.email || !form.email.includes('@')) {
    errorMsg.value = '请输入有效的邮箱地址'
    return
  }
  codeCooldown.value = 60
  const timer = setInterval(() => {
    codeCooldown.value--
    if (codeCooldown.value <= 0) clearInterval(timer)
  }, 1000)
  try {
    await sendEmailVerify(form.email)
    showToast('验证码已发送')
  } catch (e: any) {
    errorMsg.value = e?.message || '验证码发送失败'
    codeCooldown.value = 0
    clearInterval(timer)
  }
}

async function handleRegister() {
  if (!form.email || !form.password || !form.emailCode) {
    errorMsg.value = '请填写完整信息'
    return
  }
  if (form.password.length < 8) {
    errorMsg.value = '密码至少 8 位'
    return
  }
  if (form.password !== form.passwordConfirm) {
    errorMsg.value = '两次密码不一致'
    return
  }
  if (!form.agree) {
    errorMsg.value = '请阅读并同意用户协议'
    return
  }

  loading.value = true
  errorMsg.value = ''
  try {
    await register({
      email: form.email,
      password: form.password,
      password_confirm: form.passwordConfirm,
      invite_code: form.inviteCode || undefined,
      email_code: form.emailCode,
    })
    await authStore.fetchUser()
    showToast('注册成功，正在跳转...')
    const redirect = (route.query.redirect as string) || '/dashboard'
    clearInviteCode()
    setTimeout(() => { router.push(redirect) }, 800)
  } catch (e: any) {
    errorMsg.value = e?.message || '注册失败，请稍后重试'
  } finally {
    loading.value = false
  }
}

let globeInstance: any = null
onMounted(async () => {
  // Track invite code page view if present (also sync URL → sessionStorage)
  const code = (route.query.code as string) || getInviteCode()
  if (route.query.code) setInviteCode(route.query.code as string)
  if (code) {
    trackInvitePv(code).catch(() => {})
  }
  if (!globeEl.value) return
  try {
    const { VP92GlobeNetwork } = await import('@/utils/globe')
    globeInstance = new VP92GlobeNetwork(globeEl.value)
  } catch { /* ok */ }
})
onUnmounted(() => {
  if (globeInstance) { globeInstance.dispose(); globeInstance = null }
})
</script>

<style scoped>
.login-layout { display: flex; height: 100vh; }
.globe-side { flex: 0 0 62%; position: relative; background: #0a1628; overflow: hidden; }
.globe-canvas { width: 100%; height: 100%; }
.globe-overlay { position: absolute; bottom: 0; left: 0; width: 100%; height: 40%; background: linear-gradient(to top, #0a1628, transparent); pointer-events: none; }
.globe-brand { position: absolute; top: 32px; left: 40px; display: flex; align-items: center; gap: 12px; z-index: 10; }
.globe-brand-logo { width: 42px; height: 42px; background: linear-gradient(135deg, #0066ff, #6600ff); border-radius: 12px; display: flex; align-items: center; justify-content: center; object-fit: contain; }
.globe-brand-logo span { color: #fff; font-weight: 800; font-size: 16px; }
.globe-brand-name { font-size: 22px; font-weight: 800; color: #fff; }
.globe-brand-sub { font-size: 12px; color: rgba(255,255,255,0.5); margin-top: 2px; }

.form-side { flex: 1; display: flex; align-items: center; justify-content: center; background: #f7f9fb; position: relative; overflow-y: auto; }
.register-card { width: 400px; padding: 40px 36px; background: rgba(255,255,255,0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.6); border-radius: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.06); }
.register-card .logo { width: 56px; height: 56px; background: linear-gradient(135deg, #0066ff, #6600ff); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 20px rgba(0,102,255,0.3); object-fit: contain; }
.register-card .logo span { color: #fff; font-weight: 800; font-size: 20px; }
.register-card h1 { text-align: center; font-size: 24px; font-weight: 800; color: #191c1e; margin-bottom: 4px; }
.register-card .subtitle { text-align: center; font-size: 14px; color: #424656; margin-bottom: 28px; }
.input-group { margin-bottom: 16px; }
.input-group label { display: block; font-size: 13px; font-weight: 600; color: #191c1e; margin-bottom: 6px; }
.input-group .optional { color: #999; font-weight: 400; }
.input-group input { width: 100%; padding: 12px 16px; border: 1.5px solid #c2c6d8; border-radius: 12px; font-size: 14px; font-family: 'Inter', sans-serif; background: #fff; color: #191c1e; outline: none; transition: all 0.2s; box-sizing: border-box; }
.input-group input:focus { border-color: #0066ff; box-shadow: 0 0 0 3px rgba(0,102,255,0.1); }
.input-group input::placeholder { color: #999; }
.input-group input[readonly] { background: #f0f1f3; color: #666; cursor: not-allowed; border-color: #d8dbe2; }
.input-group-with-btn { display: flex; gap: 8px; }
.input-group-with-btn input { flex: 1; }
.code-btn { padding: 0 14px; background: #f0f4ff; color: #0050cb; border: 1.5px solid #d0e1ff; border-radius: 12px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; white-space: nowrap; }
.code-btn:hover:not(:disabled) { background: #dce8ff; }
.code-btn:disabled { background: #e0e3e5; color: #999; border-color: #e0e3e5; cursor: not-allowed; }
.email-tip { font-size: 12px; color: #888; margin: -4px 0 8px; display: flex; align-items: center; gap: 2px; line-height: 1.5; }
.email-tip .material-symbols-outlined { color: #f5a623; }
.agree-row { display: flex; align-items: flex-start; gap: 8px; margin-bottom: 20px; font-size: 13px; color: #424656; }
.agree-row input { width: 16px; height: 16px; accent-color: #0066ff; cursor: pointer; flex-shrink: 0; margin-top: 2px; }
.agree-row a { color: #0066ff; text-decoration: none; font-weight: 600; }
.agree-row a:hover { text-decoration: underline; }
.register-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #0066ff, #0050cb); color: #fff; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; }
.register-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(0,102,255,0.35); }
.register-btn:active:not(:disabled) { transform: scale(0.98); }
.register-btn:disabled { opacity: 0.7; cursor: not-allowed; }
.error-msg { margin-top: 12px; padding: 10px 14px; background: #fff2f2; color: #ba1a1a; border-radius: 10px; font-size: 13px; font-weight: 600; }
.divider { display: flex; align-items: center; gap: 16px; margin: 20px 0; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e0e3e5; }
.divider span { font-size: 12px; color: #999; }
.register { text-align: center; font-size: 14px; color: #424656; }
.register a { color: #0066ff; font-weight: 600; text-decoration: none; }
.register a:hover { text-decoration: underline; }

.toast { position: fixed; top: 24px; right: 24px; background: #191c1e; color: #fff; padding: 14px 24px; border-radius: 12px; font-size: 14px; font-weight: 600; z-index: 999; opacity: 0; transform: translateY(-10px); transition: all 0.3s; pointer-events: none; }
.toast.show { opacity: 1; transform: translateY(0); }

@media (max-width: 768px) {
  .globe-side { display: none; }
  .form-side { width: 100%; }
  .register-card { padding: 32px 20px; margin: 16px; width: 100%; box-sizing: border-box; }
}
</style>
