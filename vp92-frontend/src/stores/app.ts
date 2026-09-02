import { defineStore } from 'pinia'
import { ref } from 'vue'
import { saveLocale } from '@/api/client'

export const useAppStore = defineStore('app', () => {
  const sidebarCollapsed = ref(false)
  const currentLocale = ref('zh-CN')
  const showLanguageModal = ref(false)
  const brandName = ref('VP92')

  function toggleSidebar() {
    sidebarCollapsed.value = !sidebarCollapsed.value
  }

  function setLocale(locale: string) {
    currentLocale.value = locale
    saveLocale(locale)
  }

  return {
    sidebarCollapsed,
    currentLocale,
    showLanguageModal,
    brandName,
    toggleSidebar,
    setLocale,
  }
})
