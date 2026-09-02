<template>
  <aside class="sidebar" :class="{ open: isOpen }">
    <div class="sidebar-brand">
      <img v-if="configStore.guestConfig.logo" :src="configStore.guestConfig.logo" class="brand-logo" alt="Logo"/>
      <div v-else class="brand-logo"><span>VP</span></div>
      <div class="brand-name">VP92</div>
    </div>

    <nav class="sidebar-nav">
      <router-link to="/dashboard" class="nav-item" :class="{ active: $route.path === '/dashboard' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">dashboard</span>
        <span>{{ t('nav.dashboard') }}</span>
      </router-link>
      <router-link to="/dashboard/nodes" class="nav-item" :class="{ active: $route.path === '/dashboard/nodes' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">dns</span>
        <span>{{ t('nav.nodes') }}</span>
      </router-link>
      <router-link to="/dashboard/plans" class="nav-item" :class="{ active: $route.path === '/dashboard/plans' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">shopping_cart</span>
        <span>{{ t('nav.plans') }}</span>
      </router-link>
      <router-link to="/dashboard/orders" class="nav-item" :class="{ active: $route.path === '/dashboard/orders' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">receipt_long</span>
        <span>{{ t('nav.orders') }}</span>
      </router-link>
      <router-link to="/dashboard/tickets" class="nav-item" :class="{ active: $route.path === '/dashboard/tickets' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">support_agent</span>
        <span>{{ t('nav.tickets') }}</span>
      </router-link>
      <a class="nav-item ai-chat-btn" @click.prevent="openAiChat">
        <span class="material-symbols-outlined">smart_toy</span>
        <span>{{ t('nav.aiChat') }}</span>
      </a>
      <router-link to="/dashboard/invite" class="nav-item" :class="{ active: $route.path === '/dashboard/invite' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">card_giftcard</span>
        <span>{{ t('nav.invite') }}</span>
      </router-link>
      <router-link to="/dashboard/giftcard" class="nav-item" :class="{ active: $route.path === '/dashboard/giftcard' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">redeem</span>
        <span>{{ t('nav.giftcard') }}</span>
      </router-link>
      <router-link to="/dashboard/settings" class="nav-item" :class="{ active: $route.path === '/dashboard/settings' }" @click="$emit('navigate')">
        <span class="material-symbols-outlined">settings</span>
        <span>{{ t('nav.settings') }}</span>
      </router-link>
    </nav>

    <div class="sidebar-footer">
      <div class="user-mini">
        <div class="user-avatar">{{ avatarLetter }}</div>
        <div class="user-info">
          <div class="user-name">{{ userEmail }}</div>
          <div class="user-plan">{{ userPlan }}</div>
        </div>
      </div>
      <button class="logout-btn" @click="handleLogout">
        <span class="material-symbols-outlined">logout</span>
        <span>{{ t('nav.logout') }}</span>
      </button>
    </div>
  </aside>
</template>

<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useRouter } from 'vue-router'
import { removeToken } from '@/api/client'
import { useAuthStore } from '@/stores/auth'
import { useConfigStore } from '@/stores/config'
import { getUserInfo, getSubscribe } from '@/api/user'
import type { UserInfo, SubscribeInfo } from '@/api/types'

defineProps<{
  isOpen: boolean
}>()

defineEmits(['close', 'navigate'])

const { t } = useI18n()
const router = useRouter()
const authStore = useAuthStore()
const configStore = useConfigStore()

const userInfo = ref<UserInfo | null>(null)
const subscribeInfo = ref<SubscribeInfo | null>(null)

const userEmail = computed(() => userInfo.value?.email || authStore.user?.email || '用户')
const userPlan = computed(() => {
  if (subscribeInfo.value?.plan?.name) return subscribeInfo.value.plan.name
  if (userInfo.value?.plan_id) return 'VIP ' + userInfo.value.plan_id
  return '免费'
})
const avatarLetter = computed(() => userEmail.value.charAt(0).toUpperCase())

onMounted(async () => {
  try {
    const [userRes, subRes] = await Promise.all([getUserInfo(), getSubscribe()])
    userInfo.value = (userRes as any)?.data ?? null
    subscribeInfo.value = (subRes as any)?.data ?? null
  } catch {
    // sidebar silent fail
  }
})

function handleLogout() {
  removeToken()
  router.push('/auth/login')
}

function openAiChat() {
  const btn = document.getElementById('vp92-chat-btn') as HTMLButtonElement | null
  const box = document.getElementById('vp92-chat-box') as HTMLElement | null
  if (btn) btn.click()
  else if (box) box.classList.add('open')
}
</script>

<style scoped>
.ai-chat-btn {
  cursor: pointer;
  background: rgba(0, 102, 255, 0.08);
  border-left: 3px solid #0066ff;
}
.ai-chat-btn:hover {
  background: rgba(0, 102, 255, 0.15);
}
</style>
