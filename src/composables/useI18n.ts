import { computed } from 'vue'
import { useShoppingStore } from '@/stores/shopping'
import { translate, type Locale } from '@/lib/i18n'

export function useI18n() {
  const store = useShoppingStore()

  const locale = computed<Locale>({
    get: () => store.locale,
    set: (value) => {
      store.setLocale(value)
    }
  })

  const t = (key: string, params?: Record<string, string | number>) => {
    return translate(store.locale, key, params)
  }

  return {
    t,
    locale
  }
}
