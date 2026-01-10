<template>
  <span :class="classes" v-bind="attrs">
    <slot />
  </span>
</template>

<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({ inheritAttrs: false })
const attrs = useAttrs()

const classes = computed(() =>
  cn('inline-flex items-center rounded-full bg-muted/60 px-2.5 py-1 text-xs font-semibold', attrs.class as string)
)
</script>
