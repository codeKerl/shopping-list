<template>
  <select
    :value="modelValue"
    :class="classes"
    :disabled="disabled"
    v-bind="attrs"
    @change="$emit('update:modelValue', ($event.target as HTMLSelectElement).value)"
  >
    <slot />
  </select>
</template>

<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'

defineOptions({ inheritAttrs: false })

interface Props {
  modelValue?: string
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  disabled: false
})

defineEmits<{ 'update:modelValue': [value: string] }>()

const attrs = useAttrs()
const classes = computed(() =>
  cn(
    'h-10 w-full rounded-md border border-input bg-transparent px-3 text-sm outline-none transition focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 focus-visible:ring-offset-background',
    attrs.class as string
  )
)
</script>
