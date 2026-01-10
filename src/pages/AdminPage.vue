<template>
  <div class="grid gap-6 lg:grid-cols-2">
    <Card class="space-y-6">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Verwaltung</p>
        <h2 class="text-2xl font-semibold">Kategorien</h2>
      </div>
      <form class="flex gap-3" @submit.prevent="addCategory">
        <Input v-model="newCategory" placeholder="Neue Kategorie" />
        <Button type="submit" :disabled="!newCategory.trim()">Anlegen</Button>
      </form>
      <div class="space-y-2">
        <div
          v-for="category in store.categories"
          :key="category.id"
          class="flex items-center justify-between rounded-xl border border-input bg-card p-3"
        >
          <span class="text-sm font-semibold">{{ category.name }}</span>
          <Badge class="bg-secondary/15 text-secondary">
            {{ productCount(category.id) }} Produkte
          </Badge>
        </div>
        <div v-if="store.categories.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
          Lege zuerst Kategorien an, damit Produkte einsortiert werden koennen.
        </div>
      </div>
    </Card>

    <Card class="space-y-6">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Verwaltung</p>
        <h2 class="text-2xl font-semibold">Supermaerkte</h2>
      </div>
      <form class="flex gap-3" @submit.prevent="addStore">
        <Input v-model="newStore" placeholder="Neuer Supermarkt" />
        <Button type="submit" :disabled="!newStore.trim()">Anlegen</Button>
      </form>
      <div class="space-y-4">
        <div v-for="storeItem in store.stores" :key="storeItem.id" class="rounded-2xl border border-input bg-card p-4">
          <div class="mb-4 flex items-center justify-between">
            <div>
              <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Markt</p>
              <h3 class="text-lg font-semibold">{{ storeItem.name }}</h3>
            </div>
            <Badge class="bg-primary/10 text-primary">Reihenfolge</Badge>
          </div>
          <div class="space-y-2">
            <div
              v-for="category in orderedCategories(storeItem)"
              :key="category.id"
              class="flex items-center justify-between rounded-xl border border-input bg-muted/40 p-3 text-sm"
              draggable="true"
              @dragstart="onDragStart(category.id)"
              @dragover.prevent
              @drop="onDrop(storeItem.id, category.id)"
            >
              <span class="font-medium">{{ category.name }}</span>
              <span class="text-xs text-muted-foreground">Ziehen</span>
            </div>
            <div v-if="store.categories.length === 0" class="rounded-xl border border-dashed border-input p-4 text-xs text-muted-foreground">
              Noch keine Kategorien vorhanden.
            </div>
          </div>
        </div>
        <div v-if="store.stores.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
          Lege einen Supermarkt an, um die Reihenfolge der Kategorien zu definieren.
        </div>
      </div>
    </Card>
  </div>
</template>

<script setup lang="ts">
import { ref } from 'vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Badge from '@/components/ui/Badge.vue'
import { useShoppingStore, type StoreConfig, type Category } from '@/stores/shopping'

const store = useShoppingStore()
const newCategory = ref('')
const newStore = ref('')
const draggedId = ref<string | null>(null)

const addCategory = () => {
  const name = newCategory.value.trim()
  if (!name) return
  store.addCategory(name)
  newCategory.value = ''
}

const addStore = () => {
  const name = newStore.value.trim()
  if (!name) return
  store.addStore(name)
  newStore.value = ''
}

const productCount = (categoryId: string) => {
  return store.products.filter((product) => product.categoryId === categoryId).length
}

const orderedCategories = (storeItem: StoreConfig): Category[] => {
  const ids = storeItem.categoryOrder.filter((id) => store.categories.some((cat) => cat.id === id))
  const missing = store.categories.filter((cat) => !ids.includes(cat.id))
  return [...ids.map((id) => store.categoryById(id)!).filter(Boolean), ...missing]
}

const onDragStart = (id: string) => {
  draggedId.value = id
}

const onDrop = (storeId: string, targetId: string) => {
  if (!draggedId.value || draggedId.value === targetId) return
  const storeItem = store.stores.find((item) => item.id === storeId)
  if (!storeItem) return
  const ordered = orderedCategories(storeItem).map((category) => category.id)
  const from = ordered.indexOf(draggedId.value)
  const to = ordered.indexOf(targetId)
  if (from === -1 || to === -1) return
  ordered.splice(to, 0, ordered.splice(from, 1)[0])
  store.setStoreOrder(storeId, ordered)
  draggedId.value = null
}
</script>
