<template>
  <div class="login-layout">
    <!-- Left: 3D Globe -->
    <div class="globe-side">
      <div class="globe-brand">
        <img v-if="configStore.guestConfig.logo" :src="configStore.guestConfig.logo" class="globe-brand-logo" alt="Logo"/>
        <div v-else class="globe-brand-logo"><span>VP</span></div>
        <div>
          <div class="globe-brand-name">{{ t('common.brandName') }}</div>
          <div class="globe-brand-sub">Stable &middot; Fast &middot; Secure</div>
        </div>
      </div>
      <div ref="globeEl" class="globe-canvas"></div>
      <div class="globe-overlay"></div>
    </div>

    <!-- Right: Login Form -->
    <div class="form-side">
      <div class="login-card">
        <img v-if="configStore.guestConfig.logo" :src="configStore.guestConfig.logo" class="logo" alt="Logo"/>
        <div v-else class="logo"><span>VP</span></div>
        <h1>{{ t('auth.login.title') }}</h1>
        <p class="subtitle">{{ t('auth.login.subtitle') }}</p>

        <form @submit.prevent="handleLogin">
          <div class="input-group">
            <label>{{ t('auth.login.email') }}</label>
            <input
              v-model="email"
              type="email"
              :placeholder="t('auth.login.emailPlaceholder')"
              required
              autocomplete="email"
            />
          </div>
          <div class="input-group">
            <label>{{ t('auth.login.password') }}</label>
            <input
              v-model="password"
              type="password"
              :placeholder="t('auth.login.passwordPlaceholder')"
              required
              autocomplete="current-password"
            />
          </div>
          <div class="input-row">
            <label class="remember">
              <input v-model="remember" type="checkbox" />
              {{ t('auth.login.remember') }}
            </label>
            <router-link to="/auth/forgot" class="forgot">{{ t('auth.login.forgot') }}</router-link>
          </div>
          <button type="submit" class="login-btn" :disabled="loading">
            <span v-if="loading" class="vp-spinner vp-spinner-sm"></span>
            <span v-else>{{ t('auth.login.submit') }}</span>
          </button>
        </form>

        <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>

        <div class="divider"><span>{{ t('common.or') }}</span></div>
        <div class="register">
          {{ t('auth.login.noAccount') }}<router-link :to="{ name: 'register', query: route.query }">{{ t('auth.login.register') }}</router-link>
        </div>
      </div>
    </div>

    <!-- Toast -->
    <Teleport to="body">
      <div class="toast" :class="{ show: toastVisible }">{{ toastMsg }}</div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { login } from '@/api/auth'
import { useAuthStore } from '@/stores/auth'
import { useConfigStore } from '@/stores/config'

const router = useRouter()
const route = useRoute()
const authStore = useAuthStore()
const configStore = useConfigStore()
const { t } = useI18n()

const email = ref('')
const password = ref('')
const remember = ref(true)
const loading = ref(false)
const errorMsg = ref('')
const toastMsg = ref('')
const toastVisible = ref(false)
const globeEl = ref<HTMLElement | null>(null)

function showToast(msg: string) {
  toastMsg.value = msg
  toastVisible.value = true
  setTimeout(() => { toastVisible.value = false }, 2500)
}

async function handleLogin() {
  if (!email.value || !password.value) {
    errorMsg.value = t('auth.login.completeEmailPassword')
    return
  }
  loading.value = true
  errorMsg.value = ''
  try {
    await login({ email: email.value, password: password.value })
    await authStore.fetchUser()
    showToast(t('auth.login.loginSuccess'))
    const redirect = (route.query.redirect as string) || '/dashboard'
    setTimeout(() => { router.push(redirect) }, 800)
  } catch (e: any) {
    errorMsg.value = e?.message || t('auth.login.loginFailed')
  } finally {
    loading.value = false
  }
}

// Globe background - lazy load
let globeInstance: any = null
onMounted(async () => {
  if (!globeEl.value) return
  try {
    const { VP92GlobeNetwork } = await import('@/utils/globe')
    globeInstance = new VP92GlobeNetwork(globeEl.value)
  } catch {
    // Globe not available, that's ok
  }
})

onUnmounted(() => {
  if (globeInstance) {
    globeInstance.dispose()
    globeInstance = null
  }
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

.form-side { flex: 1; display: flex; align-items: center; justify-content: center; background: #f7f9fb; position: relative; }
.login-card { width: 380px; padding: 48px 40px; background: rgba(255,255,255,0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.6); border-radius: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.06); }
.login-card .logo { width: 56px; height: 56px; background: linear-gradient(135deg, #0066ff, #6600ff); border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; box-shadow: 0 4px 20px rgba(0,102,255,0.3); object-fit: contain; }
.login-card .logo span { color: #fff; font-weight: 800; font-size: 20px; }
.login-card h1 { text-align: center; font-size: 24px; font-weight: 800; color: #191c1e; margin-bottom: 4px; }
.login-card .subtitle { text-align: center; font-size: 14px; color: #424656; margin-bottom: 32px; }
.input-group { margin-bottom: 20px; }
.input-group label { display: block; font-size: 13px; font-weight: 600; color: #191c1e; margin-bottom: 6px; }
.input-group input { width: 100%; padding: 12px 16px; border: 1.5px solid #c2c6d8; border-radius: 12px; font-size: 14px; font-family: 'Inter', sans-serif; background: #fff; color: #191c1e; outline: none; transition: all 0.2s; box-sizing: border-box; }
.input-group input:focus { border-color: #0066ff; box-shadow: 0 0 0 3px rgba(0,102,255,0.1); }
.input-group input::placeholder { color: #999; }
.input-row { display: flex; justify-content: space-between; align-items: center; margin-bottom: 24px; }
.remember { display: flex; align-items: center; gap: 8px; font-size: 13px; color: #424656; cursor: pointer; }
.remember input { width: 16px; height: 16px; accent-color: #0066ff; cursor: pointer; }
.forgot { font-size: 13px; color: #0066ff; text-decoration: none; font-weight: 600; }
.forgot:hover { text-decoration: underline; }
.login-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #0066ff, #0050cb); color: #fff; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; }
.login-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(0,102,255,0.35); }
.login-btn:active:not(:disabled) { transform: scale(0.98); }
.login-btn:disabled { opacity: 0.7; cursor: not-allowed; }
.error-msg { margin-top: 12px; padding: 10px 14px; background: #fff2f2; color: #ba1a1a; border-radius: 10px; font-size: 13px; font-weight: 600; }
.divider { display: flex; align-items: center; gap: 16px; margin: 24px 0; }
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
  .login-card { padding: 36px 24px; margin: 16px; width: 100%; box-sizing: border-box; }
}
</style>
