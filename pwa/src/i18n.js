import { createI18n } from 'vue-i18n'
import ar from './locales/ar.json'
import en from './locales/en.json'

const STORAGE_KEY = 'helpdesk.locale'

export const i18n = createI18n({
  legacy: false,
  locale: localStorage.getItem(STORAGE_KEY) || 'ar',
  fallbackLocale: 'ar',
  messages: { ar, en },
  // Arabic plural forms: zero | one | two | few (3–10) | many (11–99) | other
  pluralRules: {
    ar: (n) => {
      if (n === 0) return 0
      if (n === 1) return 1
      if (n === 2) return 2
      if (n % 100 >= 3 && n % 100 <= 10) return 3
      if (n % 100 >= 11) return 4
      return 5
    },
  },
})

export function applyLocale(locale) {
  i18n.global.locale.value = locale
  localStorage.setItem(STORAGE_KEY, locale)
  document.documentElement.lang = locale
  document.documentElement.dir = locale === 'ar' ? 'rtl' : 'ltr'
}

/** Pick the Arabic or English name of a record for the current language. */
export function localName(record) {
  if (!record) return ''
  return i18n.global.locale.value === 'en' && record.name_en ? record.name_en : record.name_ar
}

export function currentLocale() {
  return i18n.global.locale.value
}
