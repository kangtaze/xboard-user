<script setup lang="ts">
import { onMounted, watch } from 'vue'
import { useConfigStore } from '@/stores/config'
import { useAuthStore } from '@/stores/auth'

const authStore = useAuthStore()
const configStore = useConfigStore()

onMounted(async () => {
  await configStore.loadGuestConfig()
  if (authStore.token) {
    await authStore.fetchUser()
  }

  // 加载 AI 客服聊天组件
  loadChatWidget()
})

// 登录状态变化时更新聊天 API（游客/用户）
// widget 内部每次发送都会动态读 window.VP92_CHAT_CONFIG + localStorage，
// 这里只需把 token 同步到全局 config，下次请求即生效。
watch(() => authStore.token, (newToken) => {
  updateChatWidgetApi(newToken || '')
})

function loadChatWidget() {
  if (document.getElementById('vp92-chat-widget')) return

  // 先把当前 token 写入 widget 配置（落地页路径下 widget 会自己读 localStorage，
  // 这里只是双保险）。widget 脚本会同步执行，闭包读取后即可见。
  window.VP92_CHAT_CONFIG = {
    apiUrl: authStore.token
      ? '/api/v1/user/ai/chat'
      : '/api/v1/guest/ai/chat',
    authToken: authStore.token || '',
  }

  const script = document.createElement('script')
  script.src = '/js/vp92-chat-widget.js'
  script.async = true
  document.head.appendChild(script)
}

function updateChatWidgetApi(token: string) {
  // widget 在每次 send() 内会重新读取这个对象，无需 reload 脚本
  window.VP92_CHAT_CONFIG = {
    apiUrl: token ? '/api/v1/user/ai/chat' : '/api/v1/guest/ai/chat',
    authToken: token,
  }
}
</script>

<template>
  <router-view />
</template>
