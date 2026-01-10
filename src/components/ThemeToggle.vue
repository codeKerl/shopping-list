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

const { mode, setMode } = useTheme()

const options = computed(() => [
  { value: 'system' as ThemeMode, label: 'System' },
  { value: 'light' as ThemeMode, label: 'Hell' },
  { value: 'dark' as ThemeMode, label: 'Dunkel' }
])
</script>
