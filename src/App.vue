<template>
  <div class="min-h-screen gradient-surface">
    <header class="border-b border-white/40 bg-card/70 backdrop-blur">
      <div class="container flex flex-col gap-4 py-6 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('app.subtitle') }}</p>
          <h1 class="text-3xl font-semibold">{{ t('app.title') }}</h1>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <nav class="flex items-center gap-3 text-sm font-semibold">
            <RouterLink class="rounded-full px-3 py-1 transition hover:bg-muted" to="/">{{ t('nav.list') }}</RouterLink>
            <RouterLink class="rounded-full px-3 py-1 transition hover:bg-muted" to="/admin">{{ t('nav.admin') }}</RouterLink>
          </nav>
          <div class="flex items-center gap-2 rounded-full border border-input bg-card/80 px-3 py-1 text-xs">
            <span :class="isOnline ? 'text-secondary' : 'text-destructive'" class="font-semibold">
              {{ isOnline ? t('sync.online') : t('sync.offline') }}
            </span>
            <span class="text-muted-foreground">{{ t('sync.queue', { count: queueCount }) }}</span>
            <Button size="sm" variant="outline" :disabled="isSyncing || !isOnline" @click="syncNow">
              {{ isSyncing ? t('sync.syncing') : t('sync.now') }}
            </Button>
          </div>
          <ThemeToggle />
        </div>
      </div>
    </header>

    <main class="container py-10">
      <RouterView />
    </main>
  </div>
</template>

<script setup lang="ts">
import ThemeToggle from '@/components/ThemeToggle.vue'
import Button from '@/components/ui/Button.vue'
import { useSync } from '@/composables/useSync'
import { useI18n } from '@/composables/useI18n'

const { isOnline, isSyncing, queueCount, syncNow } = useSync()
const { t } = useI18n()
</script>
