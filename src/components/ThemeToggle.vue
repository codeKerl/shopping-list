<template>
  <div class="flex items-center gap-2 rounded-full border border-input bg-card/80 px-3 py-1 text-xs">
    <button
      v-for="option in options"
      :key="option.value"
      class="rounded-full px-2 py-1 text-xs font-semibold transition"
      :class="mode === option.value ? 'bg-primary text-primary-foreground shadow-glow' : 'text-muted-foreground hover:text-foreground'"
      @click="setMode(option.value)"
    >
      {{ option.label }}
    </button>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useTheme, type ThemeMode } from '@/composables/useTheme'
import { useI18n } from '@/composables/useI18n'

const { mode, setMode } = useTheme()
const { t } = useI18n()

const options = computed(() => [
  { value: 'system' as ThemeMode, label: t('theme.system') },
  { value: 'light' as ThemeMode, label: t('theme.light') },
  { value: 'dark' as ThemeMode, label: t('theme.dark') }
])
</script>
