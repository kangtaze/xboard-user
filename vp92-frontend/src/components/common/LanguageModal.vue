<script setup lang="ts">
import { ref, computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'
import { useAppStore } from '@/stores/app'

const { locale, t } = useI18n()
const appStore = useAppStore()

const LOCALE_CODES = ['zh-CN', 'zh-TW', 'en-US'] as const
const languages = computed(() =>
  LOCALE_CODES.map(code => ({
    code,
    label: t(`languages.${code}.label`),
    native: t(`languages.${code}.native`),
  }))
)

const selectedCode = ref(locale.value)

// Sync with current locale when modal opens
watch(() => appStore.showLanguageModal, (val) => {
  if (val) {
    selectedCode.value = locale.value
  }
})

function selectLanguage(code: string) {
  selectedCode.value = code
}

function confirm() {
  locale.value = selectedCode.value
  appStore.setLocale(selectedCode.value)
  appStore.showLanguageModal = false
}

function close() {
  appStore.showLanguageModal = false
}

function handleBackdrop(e: MouseEvent) {
  if (e.target === e.currentTarget) {
    close()
  }
}
</script>

<template>
  <Transition name="modal">
    <div
      v-if="appStore.showLanguageModal"
      class="modal-backdrop"
      @click="handleBackdrop"
    >
      <div class="glass-panel modal-content animate-scale-in">
        <!-- Header -->
        <div class="modal-header">
          <div>
            <h2 class="modal-title">{{ t('languageModal.title') }}</h2>
            <p class="modal-subtitle">{{ t('languageModal.subtitle') }}</p>
          </div>
          <button class="modal-close" @click="close">
            <span class="material-symbols-outlined">close</span>
          </button>
        </div>

        <!-- Language grid -->
        <div class="lang-grid">
          <button
            v-for="lang in languages"
            :key="lang.code"
            class="lang-option"
            :class="{ 'lang-option--selected': selectedCode === lang.code }"
            @click="selectLanguage(lang.code)"
          >
            <div class="lang-radio">
              <div v-if="selectedCode === lang.code" class="lang-radio-inner" />
            </div>
            <span class="lang-label">{{ lang.label }}</span>
            <span class="lang-native font-mono">{{ lang.native }}</span>
          </button>
        </div>

        <!-- Actions -->
        <div class="modal-actions">
          <button class="racing-button-outline" @click="close">
            {{ t('common.cancel') }}
          </button>
          <button class="racing-button" @click="confirm">
            <span class="material-symbols-outlined">check</span>
            {{ t('common.confirm') }}
          </button>
        </div>
      </div>
    </div>
  </Transition>
</template>

<style scoped>
.modal-backdrop {
  position: fixed;
  inset: 0;
  z-index: 200;
  display: flex;
  align-items: center;
  justify-content: center;
  background: rgba(0, 0, 0, 0.7);
  backdrop-filter: blur(12px);
  padding: 1.5rem;
}

.modal-content {
  width: 100%;
  max-width: 520px;
  padding: 2rem;
}

.modal-header {
  display: flex;
  align-items: flex-start;
  justify-content: space-between;
  margin-bottom: 1.5rem;
}

.modal-title {
  font-family: 'Anybody', sans-serif;
  font-size: 1.25rem;
  font-weight: 800;
  color: var(--on-surface);
  letter-spacing: 0.05em;
  text-transform: uppercase;
}

.modal-subtitle {
  font-size: 0.875rem;
  color: var(--on-surface);
  opacity: 0.4;
  margin-top: 0.25rem;
}

.modal-close {
  display: flex;
  align-items: center;
  justify-content: center;
  width: 32px;
  height: 32px;
  border: 1px solid rgba(255, 255, 255, 0.08);
  border-radius: 0.25rem;
  background: transparent;
  color: var(--on-surface);
  cursor: pointer;
  transition: all var(--transition-fast);
}

.modal-close:hover {
  border-color: rgba(255, 0, 0, 0.3);
  color: var(--racing-red);
}

/* Language grid */
.lang-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 0.625rem;
  margin-bottom: 1.75rem;
}

.lang-option {
  display: flex;
  align-items: center;
  gap: 0.75rem;
  padding: 0.875rem 1rem;
  border: 1px solid rgba(255, 255, 255, 0.06);
  border-radius: 0.25rem;
  background: transparent;
  color: var(--on-surface);
  cursor: pointer;
  transition: all var(--transition-fast);
  text-align: left;
}

.lang-option:hover {
  border-color: rgba(255, 255, 255, 0.12);
  background: rgba(192, 192, 192, 0.03);
}

.lang-option--selected {
  border-color: rgba(255, 0, 0, 0.3);
  background: rgba(255, 0, 0, 0.08) !important;
  color: #fff;
}

/* Radio indicator */
.lang-radio {
  width: 16px;
  height: 16px;
  border-radius: 50%;
  border: 2px solid var(--outline);
  flex-shrink: 0;
  display: flex;
  align-items: center;
  justify-content: center;
  transition: border-color var(--transition-fast);
}

.lang-option--selected .lang-radio {
  border-color: var(--racing-red);
}

.lang-radio-inner {
  width: 8px;
  height: 8px;
  border-radius: 50%;
  background: var(--racing-red);
}

.lang-label {
  flex: 1;
  font-size: 0.875rem;
  font-weight: 600;
}

.lang-native {
  font-size: 0.6875rem;
  color: var(--metallic-silver);
  opacity: 0.6;
}

.lang-option--selected .lang-native {
  opacity: 0.8;
}

/* Actions */
.modal-actions {
  display: flex;
  justify-content: flex-end;
  gap: 0.75rem;
}

/* Transition */
.modal-enter-active,
.modal-leave-active {
  transition: opacity 0.25s ease;
}

.modal-enter-from,
.modal-leave-to {
  opacity: 0;
}

/* Responsive */
@media (max-width: 479px) {
  .lang-grid {
    grid-template-columns: 1fr;
  }

  .modal-content {
    padding: 1.5rem;
  }
}
</style>
