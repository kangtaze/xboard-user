<!--
  LanguageMenu.vue
  ----------------
  用途: Header 右上角铃铛左边的语言下拉菜单（v30 取代 v29 Settings.vue 语言卡）
  调用方: AppTopbar.vue（铃铛左边插入）
  被调用方: useI18n()（切 locale）+ useAppStore（持久化）+ localStorage（fe_locale）
  关联记录: v30 commit（撤回 v29 Settings 语言卡）
-->
<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'

const { locale, t } = useI18n()
const appStore = useAppStore()

const dropdownOpen = ref(false)
const LOCALE_CODES = ['zh-CN', 'zh-TW', 'en-US'] as const

const languages = computed(() =>
  LOCALE_CODES.map(code => ({
    code,
    label: t(`languages.${code}.label`),
    native: t(`languages.${code}.native`),
    isCurrent: code === locale.value,
  }))
)

const currentNative = computed(() => t(`languages.${locale.value}.native`))

function switchLocale(code: string) {
  if (code === locale.value) {
    dropdownOpen.value = false
    return
  }
  // CRITICAL: 双写 — 触发 vue-i18n 响应式 + 持久化到 store/localStorage
  // 参照 LanguageModal.vue line 29-30
  locale.value = code
  appStore.setLocale(code)
  dropdownOpen.value = false
}

function toggleDropdown(e: MouseEvent) {
  e.stopPropagation()
  dropdownOpen.value = !dropdownOpen.value
}

function onDocumentClick(e: MouseEvent) {
  // 参照 useNotifications.ts line 152-157 — 外部点击关闭
  if (!(e.target as HTMLElement).closest('.lang-menu-wrapper')) {
    dropdownOpen.value = false
  }
}

onMounted(() => document.addEventListener('click', onDocumentClick))
onUnmounted(() => document.removeEventListener('click', onDocumentClick))
</script>

<template>
  <div class="lang-menu-wrapper">
    <button
      class="icon-btn lang-btn"
      :title="t('languageModal.title')"
      :aria-label="t('languageModal.title')"
      @click="toggleDropdown"
    >
      <span class="material-symbols-outlined">language</span>
      <span class="lang-current-code">{{ currentNative }}</span>
    </button>
    <Transition name="lang-slide">
      <div v-if="dropdownOpen" class="lang-menu-dropdown">
        <div class="lang-menu-header">
          <span class="lang-menu-title">{{ t('languageModal.title') }}</span>
        </div>
        <button
          v-for="lang in languages"
          :key="lang.code"
          class="lang-menu-item"
          :class="{ 'lang-menu-item--active': lang.isCurrent }"
          @click="switchLocale(lang.code)"
        >
          <span class="lang-menu-radio">
            <span v-if="lang.isCurrent" class="lang-menu-radio-dot" />
          </span>
          <span class="lang-menu-label">{{ lang.label }}</span>
          <span class="lang-menu-native">{{ lang.native }}</span>
          <span v-if="lang.isCurrent" class="material-symbols-outlined lang-menu-check">check</span>
        </button>
      </div>
    </Transition>
  </div>
</template>

<style scoped>
.lang-menu-wrapper {
  position: relative;
}

.lang-btn {
  display: inline-flex;
  align-items: center;
  gap: 4px;
}

.lang-current-code {
  font-size: 11px;
  font-weight: 700;
  color: var(--on-surface, #424656);
  font-family: 'Inter', sans-serif;
  letter-spacing: 0.02em;
}

.lang-menu-dropdown {
  position: absolute;
  top: calc(100% + 8px);
  right: 0;
  width: 240px;
  background: #fff;
  border-radius: 14px;
  border: 1px solid #e0e3e5;
  box-shadow: 0 12px 40px rgba(0, 0, 0, 0.12);
  z-index: 100;
  overflow: hidden;
}

.lang-menu-header {
  padding: 12px 16px;
  border-bottom: 1px solid #f0f2f4;
}

.lang-menu-title {
  font-size: 13px;
  font-weight: 700;
  color: #191c1e;
  letter-spacing: 0.02em;
}

.lang-menu-item {
  display: flex;
  align-items: center;
  gap: 10px;
  width: 100%;
  padding: 12px 16px;
  border: none;
  background: transparent;
  cursor: pointer;
  font-family: 'Inter', sans-serif;
  text-align: left;
  transition: background 0.15s;
}

.lang-menu-item:hover {
  background: #f8faff;
}

.lang-menu-item--active {
  background: #f0f7ff;
  color: #0050cb;
}

.lang-menu-radio {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid #cbd5e1;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}

.lang-menu-item--active .lang-menu-radio {
  border-color: #0066ff;
}

.lang-menu-radio-dot {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: #0066ff;
}

.lang-menu-label {
  flex: 1;
  font-size: 13px;
  font-weight: 600;
}

.lang-menu-native {
  font-size: 11px;
  color: #94a3b8;
  opacity: 0.8;
}

.lang-menu-check {
  font-size: 16px;
  color: #0066ff;
}

/* Slide transition (mirror notif-slide in AppTopbar) */
.lang-slide-enter-active {
  transition: all 0.2s ease-out;
}
.lang-slide-leave-active {
  transition: all 0.15s ease-in;
}
.lang-slide-enter-from {
  opacity: 0;
  transform: translateY(-8px) scale(0.96);
}
.lang-slide-leave-to {
  opacity: 0;
  transform: translateY(-4px) scale(0.98);
}
</style>