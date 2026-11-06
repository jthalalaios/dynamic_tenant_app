import { createI18n } from 'vue-i18n'

// Messages are lazy-loaded per locale by helpers/language.ts
const i18n = createI18n({
  legacy: false,
  locale: 'en',
  fallbackLocale: 'en',
  missingWarn: import.meta.env.DEV,
  fallbackWarn: false,
  messages: {}
})

export default i18n
