<template>
  <div class="forgot-page">
    <div class="forgot-card">
      <img v-if="configStore.guestConfig.logo" :src="configStore.guestConfig.logo" class="logo" alt="Logo"/>
      <div v-else class="logo"><span>VP</span></div>
      <h1>{{ t('auth.forgot.title') }}</h1>
      <p class="subtitle">{{ t('auth.forgot.subtitle') }}</p>

      <form @submit.prevent="handleReset">
        <div class="input-group">
          <label>{{ t('auth.forgot.email') }}</label>
          <input v-model="form.email" type="email" :placeholder="t('auth.forgot.emailPlaceholder')" required autocomplete="email" />
        </div>
        <div class="input-group">
          <label>{{ t('auth.forgot.verifyCode') }}</label>
          <div class="input-group-with-btn">
            <input v-model="form.emailCode" type="text" :placeholder="t('common.verifyCodePlaceholder')" required maxlength="6" />
            <button type="button" class="code-btn" :disabled="codeCooldown > 0" @click="handleSendCode">
              {{ codeCooldown > 0 ? t('common.resendCooldown', { seconds: codeCooldown }) : t('auth.forgot.sendCode') }}
            </button>
          </div>
          <p class="email-tip">
            <span class="material-symbols-outlined" style="font-size:14px;vertical-align:-2px;">info</span>
            {{ t('common.emailTip') }}
          </p>
        </div>
        <div class="input-group">
          <label>{{ t('auth.forgot.newPassword') }}</label>
          <input v-model="form.newPassword" type="password" :placeholder="t('auth.forgot.newPasswordPlaceholder')" required minlength="8" autocomplete="new-password" />
        </div>

        <button type="submit" class="reset-btn" :disabled="loading">
          <span v-if="loading" class="vp-spinner vp-spinner-sm"></span>
          <span v-else>{{ t('auth.forgot.submit') }}</span>
        </button>
      </form>

      <div v-if="errorMsg" class="error-msg">{{ errorMsg }}</div>
      <div v-if="successMsg" class="success-msg">{{ successMsg }}</div>

      <div class="divider"><span>{{ t('common.or') }}</span></div>
      <div class="back-link">
        <router-link :to="{ name: 'login', query: route.query }">{{ t('auth.forgot.backLogin') }}</router-link>
        <span class="sep">|</span>
        <router-link :to="{ name: 'register', query: route.query }">{{ t('auth.forgot.registerAccount') }}</router-link>
      </div>
    </div>

    <Teleport to="body">
      <div class="toast" :class="{ show: toastVisible }">{{ toastMsg }}</div>
    </Teleport>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter, useRoute } from 'vue-router'
import { forgotPassword, sendEmailVerify } from '@/api/auth'
import { useConfigStore } from '@/stores/config'

const router = useRouter()
const route = useRoute()
const configStore = useConfigStore()
const { t } = useI18n()

const form = reactive({
  email: '',
  emailCode: '',
  newPassword: '',
})
const loading = ref(false)
const errorMsg = ref('')
const successMsg = ref('')
const codeCooldown = ref(0)
const toastMsg = ref('')
const toastVisible = ref(false)

function showToast(msg: string) {
  toastMsg.value = msg
  toastVisible.value = true
  setTimeout(() => { toastVisible.value = false }, 2500)
}

async function handleSendCode() {
  if (!form.email || !form.email.includes('@')) {
    errorMsg.value = t('common.invalidEmail')
    return
  }
  codeCooldown.value = 60
  const timer = setInterval(() => {
    codeCooldown.value--
    if (codeCooldown.value <= 0) clearInterval(timer)
  }, 1000)
  try {
    await sendEmailVerify(form.email)
    showToast(t('common.codeSent'))
  } catch (e: any) {
    errorMsg.value = e?.message || t('common.codeSendFailed')
    codeCooldown.value = 0
    clearInterval(timer)
  }
}

async function handleReset() {
  if (!form.email || !form.emailCode || !form.newPassword) {
    errorMsg.value = t('common.completeInfo')
    return
  }
  if (form.newPassword.length < 8) {
    errorMsg.value = t('common.passwordTooShort')
    return
  }
  loading.value = true
  errorMsg.value = ''
  successMsg.value = ''
  try {
    await forgotPassword({
      email: form.email,
      email_code: form.emailCode,
      new_password: form.newPassword,
    })
    successMsg.value = t('auth.forgot.resetSuccess')
    showToast(t('auth.forgot.resetSuccessToast'))
    setTimeout(() => { router.push('/auth/login') }, 2000)
  } catch (e: any) {
    errorMsg.value = e?.message || t('auth.forgot.resetFailed')
  } finally {
    loading.value = false
  }
}
</script>

<style scoped>
.forgot-page { min-height: 100vh; display: flex; align-items: center; justify-content: center; background: #f7f9fb; padding: 24px; }
.forgot-card { width: 400px; padding: 48px 40px; background: rgba(255,255,255,0.85); backdrop-filter: blur(24px); border: 1px solid rgba(255,255,255,0.6); border-radius: 24px; box-shadow: 0 8px 40px rgba(0,0,0,0.06); }
.forgot-card .logo { width: 56px; height: 56px; border-radius: 16px; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; object-fit: contain; }
.forgot-card .logo span { color: #fff; font-weight: 800; font-size: 20px; }
.forgot-card h1 { text-align: center; font-size: 24px; font-weight: 800; color: #191c1e; margin-bottom: 4px; }
.forgot-card .subtitle { text-align: center; font-size: 14px; color: #424656; margin-bottom: 32px; }
.input-group { margin-bottom: 20px; }
.input-group label { display: block; font-size: 13px; font-weight: 600; color: #191c1e; margin-bottom: 6px; }
.input-group input { width: 100%; padding: 12px 16px; border: 1.5px solid #c2c6d8; border-radius: 12px; font-size: 14px; font-family: 'Inter', sans-serif; background: #fff; color: #191c1e; outline: none; transition: all 0.2s; box-sizing: border-box; }
.input-group input:focus { border-color: #0066ff; box-shadow: 0 0 0 3px rgba(0,102,255,0.1); }
.input-group input::placeholder { color: #999; }
.input-group-with-btn { display: flex; gap: 8px; }
.email-tip { font-size: 12px; color: #888; margin: -4px 0 8px; display: flex; align-items: center; gap: 2px; line-height: 1.5; }
.email-tip .material-symbols-outlined { color: #f5a623; }
.input-group-with-btn input { flex: 1; }
.code-btn { padding: 0 14px; background: #f0f4ff; color: #0050cb; border: 1.5px solid #d0e1ff; border-radius: 12px; font-size: 13px; font-weight: 600; cursor: pointer; font-family: 'Inter', sans-serif; transition: all 0.2s; white-space: nowrap; }
.code-btn:hover:not(:disabled) { background: #dce8ff; }
.code-btn:disabled { background: #e0e3e5; color: #999; border-color: #e0e3e5; cursor: not-allowed; }
.reset-btn { width: 100%; padding: 14px; background: linear-gradient(135deg, #0066ff, #0050cb); color: #fff; border: none; border-radius: 12px; font-size: 15px; font-weight: 700; cursor: pointer; transition: all 0.2s; font-family: 'Inter', sans-serif; display: flex; align-items: center; justify-content: center; gap: 8px; margin-top: 8px; }
.reset-btn:hover:not(:disabled) { transform: translateY(-1px); box-shadow: 0 6px 24px rgba(0,102,255,0.35); }
.reset-btn:active:not(:disabled) { transform: scale(0.98); }
.reset-btn:disabled { opacity: 0.7; cursor: not-allowed; }
.error-msg { margin-top: 12px; padding: 10px 14px; background: #fff2f2; color: #ba1a1a; border-radius: 10px; font-size: 13px; font-weight: 600; }
.success-msg { margin-top: 12px; padding: 10px 14px; background: #dcfce7; color: #16a34a; border-radius: 10px; font-size: 13px; font-weight: 600; }
.divider { display: flex; align-items: center; gap: 16px; margin: 24px 0; }
.divider::before, .divider::after { content: ''; flex: 1; height: 1px; background: #e0e3e5; }
.divider span { font-size: 12px; color: #999; }
.back-link { text-align: center; font-size: 14px; color: #424656; }
.back-link a { color: #0066ff; font-weight: 600; text-decoration: none; }
.back-link a:hover { text-decoration: underline; }
.back-link .sep { margin: 0 8px; color: #c2c6d8; }

.toast { position: fixed; top: 24px; right: 24px; background: #191c1e; color: #fff; padding: 14px 24px; border-radius: 12px; font-size: 14px; font-weight: 600; z-index: 999; opacity: 0; transform: translateY(-10px); transition: all 0.3s; pointer-events: none; }
.toast.show { opacity: 1; transform: translateY(0); }

@media (max-width: 768px) {
  .forgot-card { padding: 36px 24px; margin: 16px; width: 100%; box-sizing: border-box; }
}
</style>
