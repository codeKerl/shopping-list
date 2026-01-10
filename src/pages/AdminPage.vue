<template>
  <div class="space-y-6">
    <Card class="flex flex-col gap-4">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('admin.subtitle') }}</p>
          <h2 class="text-2xl font-semibold">{{ t('admin.title') }}</h2>
        </div>
        <div class="w-full max-w-xs space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('admin.language') }}</p>
          <Select v-model="locale">
            <option value="de">Deutsch</option>
            <option value="en">English</option>
          </Select>
        </div>
      </div>
      <div class="flex flex-wrap gap-2">
        <Button :variant="activeLayer === 'stores' ? 'default' : 'outline'" size="sm" @click="activeLayer = 'stores'">
          {{ t('admin.nav.stores') }}
        </Button>
        <Button :variant="activeLayer === 'units' ? 'default' : 'outline'" size="sm" @click="activeLayer = 'units'">
          {{ t('admin.nav.units') }}
        </Button>
        <Button :variant="activeLayer === 'products' ? 'default' : 'outline'" size="sm" @click="activeLayer = 'products'">
          {{ t('admin.nav.products') }}
        </Button>
      </div>
      <p class="text-sm text-muted-foreground">{{ t('admin.navHint') }}</p>
    </Card>

    <div v-if="activeLayer === 'stores'" class="grid gap-6 lg:grid-cols-2">
      <Card class="space-y-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('admin.stores.section') }}</p>
          <h2 class="text-2xl font-semibold">{{ t('admin.categories.title') }}</h2>
        </div>
        <form class="flex gap-3" @submit.prevent="addCategory">
          <Input v-model="newCategory" :placeholder="t('admin.categories.add')" />
          <Button type="submit" :disabled="!newCategory.trim()">{{ t('admin.create') }}</Button>
        </form>
        <div class="space-y-2">
          <div
            v-for="category in store.categories"
            :key="category.id"
            class="flex items-center justify-between rounded-xl border border-input bg-card p-3"
          >
            <span class="text-sm font-semibold">{{ category.name }}</span>
          <Badge class="bg-secondary/15 text-secondary">
            {{ t('admin.productsCount', { count: productCount(category.id) }) }}
          </Badge>
          </div>
          <div v-if="store.categories.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
            {{ t('admin.categories.empty') }}
          </div>
        </div>
      </Card>

      <Card class="space-y-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('admin.stores.section') }}</p>
          <h2 class="text-2xl font-semibold">{{ t('admin.stores.title') }}</h2>
        </div>
        <form class="flex gap-3" @submit.prevent="addStore">
          <Input v-model="newStore" :placeholder="t('admin.stores.add')" />
          <Button type="submit" :disabled="!newStore.trim()">{{ t('admin.create') }}</Button>
        </form>
        <div class="space-y-4">
          <div v-for="storeItem in store.stores" :key="storeItem.id" class="rounded-2xl border border-input bg-card p-4">
            <div class="mb-4 flex items-center justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('admin.stores.section') }}</p>
                <h3 class="text-lg font-semibold">{{ storeItem.name }}</h3>
              </div>
              <Badge class="bg-primary/10 text-primary">{{ t('admin.stores.order') }}</Badge>
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
                <span class="text-xs text-muted-foreground">{{ t('admin.stores.drag') }}</span>
              </div>
              <div v-if="store.categories.length === 0" class="rounded-xl border border-dashed border-input p-4 text-xs text-muted-foreground">
                {{ t('admin.stores.noCategories') }}
              </div>
            </div>
          </div>
          <div v-if="store.stores.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
            {{ t('admin.stores.empty') }}
          </div>
        </div>
      </Card>
    </div>

    <div v-else-if="activeLayer === 'units'" class="grid gap-6 lg:grid-cols-2">
      <Card class="space-y-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('admin.nav.units') }}</p>
          <h2 class="text-2xl font-semibold">{{ t('admin.units.title') }}</h2>
        </div>
        <form class="flex gap-3" @submit.prevent="addUnit">
          <Input v-model="newUnit" :placeholder="t('admin.units.add')" />
          <Button type="submit" :disabled="!newUnit.trim()">{{ t('admin.create') }}</Button>
        </form>
        <div class="space-y-2">
          <div
            v-for="unit in store.units"
            :key="unit.id"
            class="flex items-center gap-3 rounded-xl border border-input bg-card p-3"
          >
            <Input v-model="unitEdits[unit.id]" class="flex-1" />
            <Button size="sm" variant="outline" @click="saveUnit(unit.id)">{{ t('admin.save') }}</Button>
            <button
              class="rounded-full border border-input bg-card/80 p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
              type="button"
              :aria-label="t('admin.deleteUnit')"
              @click="removeUnit(unit.id)"
            >
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>
          <div v-if="store.units.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
            {{ t('admin.units.empty') }}
          </div>
        </div>
      </Card>
      <Card class="space-y-6">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('home.note') }}</p>
          <h2 class="text-2xl font-semibold">{{ t('admin.units.examples') }}</h2>
        </div>
        <div class="space-y-3 text-sm text-muted-foreground">
          <p>{{ t('admin.units.examplesText') }}</p>
          <p>{{ t('admin.units.examplesText2') }}</p>
        </div>
      </Card>
    </div>

    <div v-else class="grid gap-6 lg:grid-cols-2">
      <Card class="space-y-6 lg:col-span-2">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('admin.nav.products') }}</p>
          <h2 class="text-2xl font-semibold">{{ t('admin.products.title') }}</h2>
        </div>
        <Input v-model="productSearch" :placeholder="t('admin.products.search')" />
        <div class="grid gap-3 md:grid-cols-2">
          <div
            v-for="product in filteredProducts"
            :key="product.id"
            class="flex items-center justify-between rounded-xl border border-input bg-card p-3"
          >
            <div>
              <p class="text-sm font-semibold">{{ product.name }}</p>
              <p class="text-xs text-muted-foreground">{{ categoryName(product.categoryId) || t('list.categoryNone') }}</p>
            </div>
            <button
              class="rounded-full border border-input bg-card/80 p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
              type="button"
              :aria-label="t('admin.deleteProduct')"
              @click="removeProduct(product.id)"
            >
              <TrashIcon class="h-4 w-4" />
            </button>
          </div>
          <div v-if="filteredProducts.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
            {{ t('admin.products.empty') }}
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
import Select from '@/components/ui/Select.vue'
import Badge from '@/components/ui/Badge.vue'
import { useShoppingStore, type StoreConfig, type Category } from '@/stores/shopping'
import { TrashIcon } from '@radix-icons/vue'
import { useI18n } from '@/composables/useI18n'

const store = useShoppingStore()
const { t, locale } = useI18n()
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
