import { defineConfig } from 'vite'
import vue from '@vitejs/plugin-vue'

export default defineConfig({
  plugins: [vue()],
  define: {
    // vue-i18n esm-bundler feature flags (app uses the Composition API only)
    __VUE_I18N_FULL_INSTALL__: true,
    __VUE_I18N_LEGACY_API__: false,
    __INTLIFY_PROD_DEVTOOLS__: false
  },
  server: {
    host: '0.0.0.0',
    port: Number(process.env.VITE_PORT) || 5173
  }
})
