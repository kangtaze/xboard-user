<template>
  <div class="dashboard-layout">
    <AppSidebar :isOpen="sidebarOpen" @close="sidebarOpen = false" @navigate="sidebarOpen = false" />
    <div class="sidebar-backdrop" :class="{ active: sidebarOpen }" @click="sidebarOpen = false"></div>
    <main class="main-content">
      <AppTopbar @toggle-sidebar="sidebarOpen = !sidebarOpen" />
      <router-view />
    </main>
    <LanguageModal />
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import AppSidebar from '@/components/layout/AppSidebar.vue'
import AppTopbar from '@/components/layout/AppTopbar.vue'
import LanguageModal from '@/components/common/LanguageModal.vue'

const sidebarOpen = ref(false)
</script>
