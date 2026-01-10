<template>
  <div class="space-y-6">
    <Card class="flex flex-col gap-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Admin</p>
        <h2 class="text-2xl font-semibold">Daten & Struktur</h2>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button :variant="activeLayer === 'stores' ? 'default' : 'outline'" size="sm" @click="activeLayer = 'stores'">
          Supermaerkte
        </Button>
        <Button :variant="activeLayer === 'units' ? 'default' : 'outline'" size="sm" @click="activeLayer = 'units'">
          Einheiten
        </Button>
        <Button :variant="activeLayer === 'products' ? 'default' : 'outline'" size="sm" @click="activeLayer = 'products'">
          Lebensmittelbestand
        </Button>
      </div>
      <p class="text-sm text-muted-foreground">
        Wechsle zwischen den Bereichen fuer Supermaerkte inkl. Kategorien, Einheiten und dem Lebensmittelbestand.
      </p>
    </Card>

    <div v-if="activeLayer === 'stores'" class="grid gap-6 lg:grid-cols-2">
      <Card class="space-y-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supermaerkte</p>
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
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supermaerkte</p>
          <h2 class="text-2xl font-semibold">Maerkte & Reihenfolge</h2>
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

    <div v-else-if="activeLayer === 'units'" class="grid gap-6 lg:grid-cols-2">
      <Card class="space-y-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Einheiten</p>
          <h2 class="text-2xl font-semibold">Pflegen</h2>
        </div>
        <form class="flex gap-3" @submit.prevent="addUnit">
          <Input v-model="newUnit" placeholder="Neue Einheit (z.B. g, kg, l)" />
          <Button type="submit" :disabled="!newUnit.trim()">Anlegen</Button>
        </form>
        <div class="space-y-2">
          <div
            v-for="unit in store.units"
            :key="unit.id"
            class="flex items-center gap-3 rounded-xl border border-input bg-card p-3"
          >
            <Input v-model="unitEdits[unit.id]" class="flex-1" />
            <Button size="sm" variant="outline" @click="saveUnit(unit.id)">Speichern</Button>
            <button
              class="rounded-full border border-input bg-card/80 p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
              type="button"
              aria-label="Einheit loeschen"
              @click="removeUnit(unit.id)"
            >
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>
          <div v-if="store.units.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
            Lege Einheiten an, um Mengen schneller einzugeben.
          </div>
        </div>
      </Card>
      <Card class="space-y-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Hinweis</p>
          <h2 class="text-2xl font-semibold">Beispiele</h2>
        </div>
        <div class="space-y-3 text-sm text-muted-foreground">
          <p>Kurze Einheiten fuer schnelle Eingabe: g, kg, l, ml, Stk.</p>
          <p>Diese Einheiten werden beim Anlegen automatisch zu Name + Menge kombiniert.</p>
        </div>
      </Card>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-2">
      <Card class="space-y-6 lg:col-span-2">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lebensmittelbestand</p>
          <h2 class="text-2xl font-semibold">Gesamtliste</h2>
        </div>
        <Input v-model="productSearch" placeholder="Schnellsuche" />
        <div class="grid gap-3 md:grid-cols-2">
          <div
            v-for="product in filteredProducts"
            :key="product.id"
            class="flex items-center justify-between rounded-xl border border-input bg-card p-3"
          >
            <div>
              <p class="text-sm font-semibold">{{ product.name }}</p>
              <p class="text-xs text-muted-foreground">{{ categoryName(product.categoryId) || 'Ohne Kategorie' }}</p>
            </div>
            <button
              class="rounded-full border border-input bg-card/80 p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
              type="button"
              aria-label="Lebensmittel loeschen"
              @click="removeProduct(product.id)"
            >
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>
          <div v-if="filteredProducts.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
            Keine Lebensmittel vorhanden.
          </div>
        </div>
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Badge from '@/components/ui/Badge.vue'
import { useShoppingStore, type StoreConfig, type Category } from '@/stores/shopping'
import { TrashIcon } from '@radix-icons/vue'

const store = useShoppingStore()
const newCategory = ref('')
const newStore = ref('')
const newUnit = ref('')
const productSearch = ref('')
const draggedId = ref<string | null>(null)
const unitEdits = ref<Record<string, string>>({})
const activeLayer = ref<'stores' | 'units' | 'products'>('stores')

onMounted(() => {
  store.units.forEach((unit) => {
    unitEdits.value[unit.id] = unit.name
  })
})

watch(
  () => store.units,
  (units) => {
    units.forEach((unit) => {
      if (!unitEdits.value[unit.id]) {
        unitEdits.value[unit.id] = unit.name
      }
    })
  },
  { deep: true }
)

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

const addUnit = () => {
  const name = newUnit.value.trim()
  if (!name) return
  store.addUnit(name)
  newUnit.value = ''
}

const saveUnit = (unitId: string) => {
  const name = unitEdits.value[unitId]?.trim()
  if (!name) return
  store.updateUnit(unitId, name)
}

const removeUnit = (unitId: string) => {
  store.removeUnit(unitId)
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

const categoryName = (id?: string) => {
  return store.categoryById(id)?.name || ''
}

const filteredProducts = computed(() => {
  const term = productSearch.value.trim().toLowerCase()
  const products = [...store.products].sort((a, b) => a.name.localeCompare(b.name))
  if (!term) return products
  return products.filter((product) => product.name.toLowerCase().includes(term))
})

const removeProduct = (id: string) => {
  store.removeProduct(id)
}
</script>
