import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import { resolve } from 'path'

export default defineConfig({
  base: '/theme/VP92/',
  plugins: [vue(), tailwindcss()],
  resolve: { alias: { '@': resolve(__dirname, 'src') } },
  server: {
    port: 3001,
    proxy: {
      '/gxapi': {
        target: 'http://154.3.38.212:7002',
        changeOrigin: true,
        rewrite: (path) => path.replace(/^\/gxapi/, '/api'),
      },
    },
  },
  build: {
    rollupOptions: {
      output: {
        manualChunks(id) {
          if (id.includes('node_modules/vue/') || id.includes('node_modules/vue-router/') || id.includes('node_modules/pinia/')) return 'vue-vendor'
          if (id.includes('node_modules/vue-i18n/')) return 'i18n'
          if (id.includes('node_modules/three/')) return 'three'
          if (id.includes('node_modules/marked/')) return 'marked'
          if (id.includes('node_modules/axios/')) return 'axios'
        },
      },
    },
  },
})
