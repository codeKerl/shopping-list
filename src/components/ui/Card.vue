<template>
  <div :class="classes" v-bind="attrs">
    <slot />
  </div>
</template>

<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({ inheritAttrs: false })
const attrs = useAttrs()

const classes = computed(() => cn('card-surface p-6', attrs.class as string))
</script>
