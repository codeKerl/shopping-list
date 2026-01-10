<template>
  <button :class="classes" :type="type" :disabled="disabled" v-bind="attrs">
    <slot />
  </button>
</template>

<script setup lang="ts">
import { computed, useAttrs } from 'vue'
import { cn } from '@/lib/utils'
import { buttonVariants, type ButtonVariants } from './button'

defineOptions({ inheritAttrs: false })

interface Props {
  variant?: ButtonVariants['variant']
  size?: ButtonVariants['size']
  type?: 'button' | 'submit' | 'reset'
  disabled?: boolean
}

const props = withDefaults(defineProps<Props>(), {
  type: 'button',
  disabled: false
})

const attrs = useAttrs()
const classes = computed(() =>
  cn(buttonVariants({ variant: props.variant, size: props.size }), attrs.class as string)
)
</script>
