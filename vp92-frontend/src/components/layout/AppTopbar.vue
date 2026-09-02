<template>
  <header class="top-bar">
    <div class="top-bar-left">
      <button class="menu-toggle" @click="$emit('toggle-sidebar')" title="菜单">
        <span class="material-symbols-outlined">menu</span>
      </button>
      <div class="page-title" id="pageTitle">{{ pageTitle }}</div>
    </div>
    <div class="top-actions">
      <LanguageMenu />
      <div class="notif-wrapper">
        <button class="icon-btn" title="通知" @click.stop="toggleDropdown">
          <span class="material-symbols-outlined">notifications</span>
          <span v-if="unreadCount > 0" class="notif-badge">{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
        </button>
        <Transition name="notif-slide">
          <div v-if="dropdownOpen" class="notif-dropdown">
            <div class="notif-header">
              <span class="notif-header-title">通知</span>
              <button v-if="notifications.length > 0" class="notif-mark-read" @click="markAllRead">全部已读</button>
            </div>
            <div class="notif-list">
              <router-link
                v-for="item in notifications"
                :key="item.id"
                :to="item.action?.route || ''"
                class="notif-item"
                :class="{ unread: !item.read }"
                @click="closeDropdown"
              >
                <div class="notif-icon" :class="item.type">
                  <span class="material-symbols-outlined">
                    {{ item.type === 'ticket_reply' ? 'support_agent' : 'check_circle' }}
                  </span>
                </div>
                <div class="notif-content">
                  <div class="notif-title">{{ item.title }}</div>
                  <div class="notif-message">{{ item.message }}</div>
                  <div class="notif-time">{{ formatTime(item.time) }}</div>
                </div>
              </router-link>
              <div v-if="notifications.length === 0" class="notif-empty">
                <span class="material-symbols-outlined">notifications_off</span>
                <span>暂无新通知</span>
              </div>
            </div>
          </div>
        </Transition>
      </div>
      <button class="icon-btn" title="设置" @click="$router.push('/dashboard/settings')">
        <span class="material-symbols-outlined">settings</span>
      </button>
    </div>
  </header>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useNotifications } from '@/composables/useNotifications'
import LanguageMenu from '@/components/layout/LanguageMenu.vue'

defineEmits(['toggle-sidebar'])

const route = useRoute()
const router = useRouter()
const pageTitle = ref('')

const { notifications, unreadCount, dropdownOpen, toggleDropdown, closeDropdown, markAllRead } = useNotifications()

function formatTime(ts: number): string {
  if (!ts) return ''
  const now = Date.now() / 1000
  const diff = now - ts
  if (diff < 60) return '刚刚'
  if (diff < 3600) return `${Math.floor(diff / 60)} 分钟前`
  if (diff < 86400) return `${Math.floor(diff / 3600)} 小时前`
  return new Date(ts * 1000).toLocaleDateString('zh-CN', { month: 'short', day: 'numeric' })
}

watch(() => route.path, () => {
  const titles: Record<string, string> = {
    '/dashboard': '控制面板',
    '/dashboard/nodes': '节点列表',
    '/dashboard/plans': '套餐购买',
    '/dashboard/orders': '我的订单',
    '/dashboard/tickets': '工单支持',
    '/dashboard/invite': '邀请返利',
    '/dashboard/giftcard': '礼品卡',
    '/dashboard/settings': '账户设置',
  }
  pageTitle.value = titles[route.path] || 'VP92'
}, { immediate: true })
</script>
