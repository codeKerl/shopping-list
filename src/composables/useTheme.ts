import { ref, watchEffect } from 'vue'

const STORAGE_KEY = 'einkaufszettel-theme'

export type ThemeMode = 'system' | 'light' | 'dark'

let hasInitialized = false

export function initTheme() {
  if (hasInitialized) return
  hasInitialized = true
  const mode = (localStorage.getItem(STORAGE_KEY) as ThemeMode) || 'system'
  const media = window.matchMedia('(prefers-color-scheme: dark)')
  const applyTheme = () => {
    const root = document.documentElement
    const shouldUseDark = mode === 'dark' || (mode === 'system' && media.matches)
    root.classList.toggle('dark', shouldUseDark)
  }
  applyTheme()
  media.addEventListener('change', applyTheme)
}

export function useTheme() {
  const mode = ref<ThemeMode>((localStorage.getItem(STORAGE_KEY) as ThemeMode) || 'system')
  const media = window.matchMedia('(prefers-color-scheme: dark)')

  const applyTheme = () => {
    const root = document.documentElement
    const shouldUseDark = mode.value === 'dark' || (mode.value === 'system' && media.matches)
    root.classList.toggle('dark', shouldUseDark)
  }

  const setMode = (value: ThemeMode) => {
    mode.value = value
    localStorage.setItem(STORAGE_KEY, value)
    applyTheme()
  }

  media.addEventListener('change', applyTheme)

  watchEffect(() => {
    applyTheme()
  })

  return {
    mode,
    setMode
  }
}
