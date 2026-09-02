<template>
  <div class="page" :class="{ active: true }">
    <div class="grid-2col gap-6">
      <!-- Left: Profile -->
      <div class="card">
        <div class="card-header">个人信息</div>
        <div v-if="loading" class="loading-state">
          <span class="material-symbols-outlined spinning">progress_activity</span>
          <span>加载中...</span>
        </div>
        <template v-else-if="user">
          <div class="account-profile">
            <div class="profile-avatar">{{ avatarLetter }}</div>
            <div class="profile-info">
              <div class="profile-label">邮箱</div>
              <div class="profile-value">{{ user.email }}</div>
              <div class="profile-label mt-4">昵称</div>
              <div class="profile-value">{{ user.email.split('@')[0] }}</div>
            </div>
          </div>
        </template>
      </div>

      <!-- Right: Invite -->
      <div class="card">
        <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
          <span>邀请好友</span>
          <router-link to="/invite" style="font-size:12px;color:#0066ff;text-decoration:none;font-weight:600;">查看详情 →</router-link>
        </div>
        <p style="font-size:13px;color:#5c6078;margin-bottom:16px;">邀请好友注册，双方均可获得奖励</p>
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

    <!-- Language -->
    <div class="card mt-6">
      <div class="card-header" style="display:flex;align-items:center;justify-content:space-between;">
        <span>{{ t('settings.language.title') }}</span>
        <button class="action-btn" @click="openLanguageModal" style="white-space:nowrap;padding:0 14px;">
          <span class="material-symbols-outlined" style="font-size:18px;">language</span>
          <span style="margin-left:6px;">{{ t('settings.language.switch') }}</span>
        </button>
      </div>
      <p style="font-size:13px;color:#5c6078;">
        {{ t('settings.language.current') }}: <strong>{{ currentLanguageLabel }}</strong>
      </p>
    </div>

    <!-- Subscription Management -->
    <div class="card mt-6">
      <div class="card-header">订阅管理</div>
      <div class="account-actions">
          <button class="action-btn" :disabled="resetting" @click="handleResetSub">
            <span v-if="resetting" class="material-symbols-outlined spinning">progress_activity</span>
            <span v-else>重置订阅链接</span>
          </button>
          <button class="action-btn" disabled>
            自定义订阅后缀
          </button>
        </div>
      </div>
    </div>

    <!-- Password -->
    <div class="card mt-6">
      <div class="card-header">修改密码</div>
      <div class="password-form">
        <div class="input-row">
          <label class="input-label">当前密码</label>
          <input
            v-model="passwords.old_password"
            type="password"
            placeholder="请输入当前密码"
            class="form-input"
          />
        </div>
        <div class="input-row">
          <label class="input-label">新密码</label>
          <input
            v-model="passwords.new_password"
            type="password"
            placeholder="请输入新密码（至少 8 位）"
            class="form-input"
          />
        </div>
        <div class="input-row">
          <label class="input-label">确认新密码</label>
          <input
            v-model="passwords.confirm_password"
            type="password"
            placeholder="请再次输入新密码"
            class="form-input"
          />
        </div>
        <button class="save-btn" :disabled="changingPwd" @click="handleChangePassword">
          <span v-if="changingPwd" class="material-symbols-outlined spinning">progress_activity</span>
          <span v-else>保存修改</span>
        </button>
      </div>
    </div>

    <!-- Third Party -->
    <div class="card mt-6">
      <div class="card-header">第三方绑定</div>
      <div class="bind-list">
        <div class="bind-item">
          <div class="bind-icon">
            <span class="material-symbols-outlined" style="font-size: 28px;">send</span>
          </div>
          <div class="bind-info">
            <div class="bind-name">Telegram</div>
            <div class="bind-status" :style="{ color: user?.telegram_id ? '#16a34a' : '#424656' }">
              {{ user?.telegram_id ? '已绑定' : '未绑定' }}
            </div>
          </div>
          <button class="bind-btn" :disabled="unbindingTelegram" @click="handleTelegramBind">
            <span v-if="unbindingTelegram" class="material-symbols-outlined spinning" style="font-size:16px;">progress_activity</span>
            <span v-else>{{ user?.telegram_id ? '解绑' : '绑定' }}</span>
          </button>
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
import { useAppStore } from '@/stores/app'
import { getUserInfo, changePassword, resetSecurity, getInviteSummary, generateInviteCode } from '@/api/user'
import type { UserInfo } from '@/api/types'

const { t, locale } = useI18n()
const appStore = useAppStore()

const languageNames: Record<string, string> = {
  'zh-CN': '简体中文',
  'zh-TW': '繁體中文',
  'en-US': 'English',
}
const currentLanguageLabel = computed(() => languageNames[locale.value] ?? locale.value)

function openLanguageModal() {
  appStore.showLanguageModal = true
}

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
  showToast('邀请链接已复制')
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
    showToast(e.message || '加载用户信息失败')
  } finally {
    loading.value = false
  }
}

async function handleChangePassword() {
  if (!passwords.old_password || !passwords.new_password) {
    showToast('请填写所有密码字段')
    return
  }
  if (passwords.new_password.length < 8) {
    showToast('新密码至少 8 位')
    return
  }
  if (passwords.new_password !== passwords.confirm_password) {
    showToast('两次输入的新密码不一致')
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
    showToast('密码修改成功')
  } catch (e: any) {
    showToast(e.message || '修改失败')
  } finally {
    changingPwd.value = false
  }
}

async function handleResetSub() {
  resetting.value = true
  try {
    await resetSecurity()
    showToast('订阅链接已重置')
  } catch (e: any) {
    showToast(e.message || '重置失败')
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
      showToast('Telegram 已解绑')
    } catch (e) {
      showToast('解绑失败，请稍后再试')
    } finally {
      unbindingTelegram.value = false
    }
  } else {
    // Bind — redirect to Telegram bot
    const botName = telegramBotUsername.value || 'vp92VPN_bot'
    window.open(`https://t.me/${botName}?start=bind`, '_blank')
    showToast('请在 Telegram 中完成绑定后刷新页面')
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
