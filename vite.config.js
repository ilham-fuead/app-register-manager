import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  base: '/my-apps/dist/',   // All URLs point to /my-apps/dist/ for both dev and prod
  server: {
    proxy: {
      '/app-manager/api': {
        target: 'http://localhost',
        changeOrigin: true,
      }
    }
  },
  build: {
    outDir: '../my-apps/dist',
    assetsDir: 'assets',
  }
})
