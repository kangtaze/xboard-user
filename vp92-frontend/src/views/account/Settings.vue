<template>
  <div class="page" :class="{ active: true }">
    <div class="grid-2col gap-6">
      <!-- Left: Profile -->
      <div class="card">
        <div class="card-header">{{ t('settings.profile.title') }}</div>
        <div v-if="loading" class="loading-state">
          <span class="material-symbols-outlined spinning">progress_activity</span>
          <span>{{ t('settings.profile.loading') }}</span>
        </div>
        <template v-else-if="user">
          <div class="account-profile">
            <div class="profile-avatar">{{ avatarLetter }}</div>
            <div class="profile-info">
              <div class="profile-label">{{ t('settings.profile.email') }}</div>
              <div class="profile-value">{{ user.email }}</div>
              <div class="profile-label mt-4">{{ t('settings.profile.nickname') }}</div>
              <div class="profile-value">{{ user.email.split('@')[0] }}</div>
            </div>
          </div>
        </template>
      </div>

      <!-- Right: Invite -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
          <span>{{ t('settings.invite.title') }}</span>
          <router-link to="/invite" style="font-size:12px;color:#0066ff;text-decoration:none;font-weight:600;">{{ t('settings.invite.viewDetail') }}</router-link>
        </div>
        <p style="font-size:13px;color:#5c6078;margin-bottom:16px;">{{ t('settings.invite.description') }}</p>
        <div style="display:flex;gap:8px;align-items:center;">
          <input
            :value="inviteUrl"
            readonly
            class="form-input"
            style="flex:1;font-size:13px;padding:10px 12px;"
            @click="($event.target as HTMLInputElement).select()"
          />
          <button class="action-btn" @click="copyInvite" style="white-space:nowrap;padding:0 14px;">
            <span class="material-symbols-outlined" style="font-size:18px;">content_copy</span>
          </button>
        </div>
        <p v-if="toast.show" style="font-size:11px;color:#16a34a;margin-top:8px;">{{ toast.message }}</p>
      </div>
    </div>

    <!-- Subscription Management -->
    <div class="card mt-6">
      <div class="card-header">{{ t('settings.subscribe.title') }}</div>
      <div class="account-actions">
        <button class="action-btn" :disabled="resetting" @click="handleResetSub">
          <span v-if="resetting" class="material-symbols-outlined spinning">progress_activity</span>
          <span v-else>{{ t('settings.subscribe.reset') }}</span>
        </button>
        <button class="action-btn" disabled>
          {{ t('settings.subscribe.customSuffix') }}
        </button>
      </div>
    </div>

    <!-- Password -->
    <div class="card mt-6">
      <div class="card-header">{{ t('settings.password.title') }}</div>
      <div class="password-form">
        <div class="input-row">
          <label class="input-label">{{ t('settings.password.currentPassword') }}</label>
          <input
            v-model="passwords.old_password"
            type="password"
            :placeholder="t('settings.password.currentPlaceholder')"
            class="form-input"
          />
        </div>
        <div class="input-row">
          <label class="input-label">{{ t('settings.password.newPassword') }}</label>
          <input
            v-model="passwords.new_password"
            type="password"
            :placeholder="t('settings.password.newPlaceholder')"
            class="form-input"
          />
        </div>
        <div class="input-row">
          <label class="input-label">{{ t('settings.password.confirmPassword') }}</label>
          <input
            v-model="passwords.confirm_password"
            type="password"
            :placeholder="t('settings.password.confirmPlaceholder')"
            class="form-input"
          />
        </div>
        <button class="save-btn" :disabled="changingPwd" @click="handleChangePassword">
          <span v-if="changingPwd" class="material-symbols-outlined spinning">progress_activity</span>
          <span v-else>{{ t('settings.password.save') }}</span>
        </button>
      </div>
    </div>

    <!-- Third Party -->
    <div class="card mt-6">
      <div class="card-header">{{ t('settings.bindings.title') }}</div>
      <div class="bind-list">
        <div class="bind-item">
          <div class="bind-icon">
            <span class="material-symbols-outlined" style="font-size: 28px;">send</span>
          </div>
          <div class="bind-info">
            <div class="bind-name">{{ t('settings.bindings.telegram') }}</div>
            <div class="bind-status" :style="{ color: user?.telegram_id ? '#16a34a' : '#424656' }">
              {{ user?.telegram_id ? t('settings.bindings.bound') : t('settings.bindings.unbound') }}
            </div>
          </div>
          <button class="bind-btn" :disabled="unbindingTelegram" @click="handleTelegramBind">
            <span v-if="unbindingTelegram" class="material-symbols-outlined spinning" style="font-size:16px;">progress_activity</span>
            <span v-else>{{ user?.telegram_id ? t('settings.bindings.unbind') : t('settings.bindings.bind') }}</span>
          </button>
        </div>
      </div>
    </div>

      <!-- Toast -->
    <div v-if="toast.show" class="toast" :class="{ show: toast.show }">
      {{ toast.message }}
    </div>
  </div>
</template>

<script setup lang="ts">
import { ref, reactive, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { getUserInfo, changePassword, resetSecurity, getInviteSummary, generateInviteCode } from '@/api/user'
import type { UserInfo } from '@/api/types'

const { t } = useI18n()

const user = ref<UserInfo | null>(null)
const loading = ref(false)
const changingPwd = ref(false)
const resetting = ref(false)
const toast = reactive({ show: false, message: '' })
const passwords = reactive({
  old_password: '',
  new_password: '',
  confirm_password: '',
})

const inviteUrl = ref('')
const telegramBotUsername = ref('')
const unbindingTelegram = ref(false)

function copyInvite() {
  navigator.clipboard.writeText(inviteUrl.value)
  showToast(t('settings.invite.copied'))
}

async function loadInviteCode() {
  try {
    const res = (await getInviteSummary()) as any
    const code = res?.data?.codes?.[0]?.code
    if (code) {
      inviteUrl.value = `${window.location.origin}/auth/register?code=${code}`
    } else {
      // Generate one if not exists
      await generateInviteCode()
      const res2 = (await getInviteSummary()) as any
      const code2 = res2?.data?.codes?.[0]?.code
      if (code2) inviteUrl.value = `${window.location.origin}/auth/register?code=${code2}`
    }
  } catch (e) {
    // Silently fail - invite is optional on this page
  }
}

const avatarLetter = computed(() => {
  if (!user.value?.email) return 'U'
  return user.value.email.charAt(0).toUpperCase()
})

function showToast(msg: string) {
  toast.message = msg
  toast.show = true
  setTimeout(() => { toast.show = false }, 2500)
}

async function loadUser() {
  loading.value = true
  try {
    const res = (await getUserInfo()) as any
    user.value = res.data || null
  } catch (e: any) {
    showToast(e.message || t('settings.profile.loadFailed'))
  } finally {
    loading.value = false
  }
}

async function handleChangePassword() {
  if (!passwords.old_password || !passwords.new_password) {
    showToast(t('settings.password.messages.fillAll'))
    return
  }
  if (passwords.new_password.length < 8) {
    showToast(t('settings.password.messages.tooShort'))
    return
  }
  if (passwords.new_password !== passwords.confirm_password) {
    showToast(t('settings.password.messages.passwordMismatch'))
    return
  }
  changingPwd.value = true
  try {
    await changePassword({
      old_password: passwords.old_password,
      new_password: passwords.new_password,
    })
    passwords.old_password = ''
    passwords.new_password = ''
    passwords.confirm_password = ''
    showToast(t('settings.password.messages.success'))
  } catch (e: any) {
    showToast(e.message || t('common.actionFailed'))
  } finally {
    changingPwd.value = false
  }
}

async function handleResetSub() {
  resetting.value = true
  try {
    await resetSecurity()
    showToast(t('settings.subscribe.messages.resetSuccess'))
  } catch (e: any) {
    showToast(e.message || t('common.actionFailed'))
  } finally {
    resetting.value = false
  }
}

async function handleTelegramBind() {
  if (user.value?.telegram_id) {
    // Unbind
    if (unbindingTelegram.value) return
    unbindingTelegram.value = true
    try {
      const token = localStorage.getItem('vp92_token') || ''
      await fetch('/api/v1/user/telegram/unbind', {
        method: 'POST',
        headers: { 'Authorization': 'Bearer ' + token, 'Content-Type': 'application/json', 'Accept': 'application/json' }
      })
      user.value = { ...user.value, telegram_id: null }
      showToast(t('settings.bindings.messages.unbindSuccess'))
    } catch (e) {
      showToast(t('common.actionFailed'))
    } finally {
      unbindingTelegram.value = false
    }
  } else {
    // Bind — redirect to Telegram bot
    const botName = telegramBotUsername.value || 'vp92VPN_bot'
    window.open(`https://t.me/${botName}?start=bind`, '_blank')
    showToast(t('settings.bindings.bindHint'))
  }
}

async function loadTelegramBotInfo() {
  try {
    const token = localStorage.getItem('vp92_token') || ''
    const res = await fetch('/api/v1/user/telegram/getBotInfo', {
      headers: { 'Authorization': 'Bearer ' + token, 'Accept': 'application/json' }
    })
    const data = await res.json()
    if (data?.data?.username) {
      telegramBotUsername.value = data.data.username
    }
  } catch {
    // Silently fail
  }
}

onMounted(() => {
  loadUser()
  loadInviteCode()
  loadTelegramBotInfo()
})
</script>

<style scoped>
.loading-state {
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
.bind-icon {
  width: 48px;
  height: 48px;
  border-radius: 12px;
  background: #f0f4ff;
  display: flex;
  align-items: center;
  justify-content: center;
  color: #0050cb;
}
.action-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
.save-btn:disabled {
  opacity: 0.6;
  cursor: not-allowed;
}
</style>
