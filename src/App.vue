<template>
  <div class="min-h-screen gradient-surface">
    <header class="border-b border-white/40 bg-card/70 backdrop-blur">
      <div class="container flex flex-col gap-4 py-6 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Familienorganisation</p>
          <h1 class="text-3xl font-semibold">Einkaufszettel</h1>
        </div>
        <div class="flex flex-wrap items-center gap-3">
          <nav class="flex items-center gap-3 text-sm font-semibold">
            <RouterLink class="rounded-full px-3 py-1 transition hover:bg-muted" to="/">Zettel</RouterLink>
            <RouterLink class="rounded-full px-3 py-1 transition hover:bg-muted" to="/admin">Admin</RouterLink>
          </nav>
          <div class="flex items-center gap-2 rounded-full border border-input bg-card/80 px-3 py-1 text-xs">
            <span :class="isOnline ? 'text-secondary' : 'text-destructive'" class="font-semibold">
              {{ isOnline ? 'Online' : 'Offline' }}
            </span>
            <span class="text-muted-foreground">Queue {{ queueCount }}</span>
            <Button size="sm" variant="outline" :disabled="isSyncing || !isOnline" @click="syncNow">
              {{ isSyncing ? 'Sync...' : 'Sync jetzt' }}
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

const { isOnline, isSyncing, queueCount, syncNow } = useSync()
</script>
