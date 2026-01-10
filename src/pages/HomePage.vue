<template>
  <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
    <Card class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Uebersicht</p>
          <h2 class="text-2xl font-semibold">Alle Einkaufszettel</h2>
        </div>
        <Button @click="createNewList">Neuen Zettel anlegen</Button>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <RouterLink
          v-for="list in lists"
          :key="list.id"
          :to="{ name: 'list', params: { id: list.id } }"
          class="rounded-2xl border border-input bg-card p-4 transition hover:bg-muted"
        >
          <div class="flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Zettel</p>
              <p class="text-lg font-semibold">{{ formatDate(list.createdAt) }}</p>
            </div>
            <Badge class="bg-accent/20 text-accent">{{ list.items.length }} Artikel</Badge>
          </div>
        </RouterLink>
        <div v-if="lists.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
          Noch kein Einkaufszettel angelegt.
        </div>
      </div>
    </Card>

    <Card class="space-y-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Hinweis</p>
        <h3 class="text-xl font-semibold">Teilen</h3>
      </div>
      <p class="text-sm text-muted-foreground">
        Jeder Zettel hat einen eigenen Link. Oeffne einen Zettel und teile die URL mit der Familie.
      </p>
      <div class="rounded-xl border border-input bg-muted/40 p-3 text-xs text-muted-foreground">
        Beispiel: /list/ABC123
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { computed } from 'vue'
import { useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Badge from '@/components/ui/Badge.vue'
import { useShoppingStore } from '@/stores/shopping'

const store = useShoppingStore()
const router = useRouter()

const lists = computed(() => store.lists)

const formatDate = (value: string) => {
  const date = new Date(value)
  return new Intl.DateTimeFormat('de-DE', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}

const createNewList = () => {
  const list = store.createList()
  router.push({ name: 'list', params: { id: list.id } })
}
</script>
