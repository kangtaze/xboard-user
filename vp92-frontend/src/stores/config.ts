import { defineStore } from 'pinia'
import { ref } from 'vue'
import { getGuestConfig } from '@/api/user'
import type { GuestConfig } from '@/api/types'

export const useConfigStore = defineStore('config', () => {
  const guestConfig = ref<GuestConfig>({
    is_email_verify: false,
    is_recaptcha: false,
    is_captcha: false,
    recaptcha_site_key: '',
    is_invite_force: false,
    logo_url: '',
    logo: (window as any).settings?.logo || null,
    currency: 'CNY',
    tos_url: null,
    app_description: '',
    app_url: '',
  })
  const loaded = ref(false)

  async function loadGuestConfig() {
    try {
      const res = (await getGuestConfig()) as any
      // res = { status, message, data: GuestConfig }
      if (res.data) {
        Object.assign(guestConfig.value, res.data)
      }
    } catch {
      // Use defaults
    } finally {
      loaded.value = true
    }
  }

  return { guestConfig, loaded, loadGuestConfig }
})
