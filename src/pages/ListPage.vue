<template>
  <div class="space-y-6">
    <Card class="flex flex-col gap-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Zettel</p>
          <h2 class="text-2xl font-semibold">
            {{ activeList ? formatDate(activeList.createdAt) : 'Zettel nicht gefunden' }}
          </h2>
        </div>
        <div class="flex flex-wrap gap-2">
          <Button :variant="mode === 'plan' ? 'default' : 'outline'" size="sm" @click="mode = 'plan'">
            Anlegen
          </Button>
          <Button :variant="mode === 'shop' ? 'default' : 'outline'" size="sm" @click="mode = 'shop'">
            Einkaufen
          </Button>
        </div>
      </div>
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div class="space-y-1">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Aktueller Zettel</p>
          <p class="text-sm text-muted-foreground">Wechsel zwischen Anlegen und Einkaufen.</p>
        </div>
        <div class="w-full max-w-xs space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Supermarkt</p>
          <Select v-model="selectedStoreId" :disabled="!activeList" @update:modelValue="setStore">
            <option value="">Kein Markt</option>
            <option v-for="storeItem in store.stores" :key="storeItem.id" :value="storeItem.id">
              {{ storeItem.name }}
            </option>
          </Select>
        </div>
      </div>
    </Card>

    <div class="grid gap-6 lg:grid-cols-[320px_1fr]" v-if="mode === 'plan'">
      <Card class="flex h-full flex-col gap-6">
        <div class="space-y-2">
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Archiv</p>
          <div class="flex items-center justify-between">
            <h2 class="text-2xl font-semibold">Einkaufszettel</h2>
            <RouterLink class="text-xs font-semibold uppercase tracking-wide text-primary" to="/">Zur Uebersicht</RouterLink>
          </div>
        </div>
        <div class="space-y-3">
          <button
            v-for="list in lists"
            :key="list.id"
            class="w-full rounded-xl border border-input p-3 text-left transition hover:bg-muted"
            :class="list.id === activeList?.id ? 'bg-muted/70' : 'bg-transparent'"
            @click="goToList(list.id)"
          >
            <p class="text-sm font-semibold">{{ formatDate(list.createdAt) }}</p>
            <p class="text-xs text-muted-foreground">
              {{ list.items.length }} Artikel
            </p>
          </button>
          <div v-if="lists.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
            Noch kein Einkaufszettel angelegt.
          </div>
        </div>
      </Card>

      <div class="space-y-6">
        <Card class="space-y-6">
          <form class="grid gap-4 md:grid-cols-[1.4fr_0.8fr_auto]" @submit.prevent="addItem">
            <div class="space-y-2">
              <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lebensmittel</label>
              <Input v-model="searchTerm" placeholder="z.B. kleine Tomaten (500g)" @focus="selectedProductId = ''" />
              <div v-if="searchTerm" class="rounded-xl border border-input bg-card p-2 text-sm">
                <p class="px-2 py-1 text-xs font-semibold uppercase tracking-wide text-muted-foreground">Vorschlaege</p>
                <div class="max-h-40 space-y-1 overflow-auto">
                  <button
                    v-for="product in filteredProducts"
                    :key="product.id"
                    class="w-full rounded-lg px-3 py-2 text-left transition hover:bg-muted"
                    @click.prevent="selectProduct(product.id)"
                  >
                    <div class="flex items-center justify-between">
                      <span class="text-sm font-medium">{{ product.name }}</span>
                      <span class="text-xs text-muted-foreground">
                        {{ categoryName(product.categoryId) || 'Ohne Kategorie' }}
                      </span>
                    </div>
                  </button>
                  <div v-if="filteredProducts.length === 0" class="px-3 py-2 text-xs text-muted-foreground">
                    Keine Treffer - neuer Eintrag wird angelegt.
                  </div>
                </div>
              </div>
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kategorie</label>
              <Select v-model="selectedCategoryId">
                <option value="">Ohne Kategorie</option>
                <option v-for="category in store.categories" :key="category.id" :value="category.id">
                  {{ category.name }}
                </option>
              </Select>
              <div class="flex flex-wrap gap-2">
                <Badge
                  v-for="category in store.categories"
                  :key="category.id"
                  class="cursor-pointer bg-primary/10 text-primary"
                  @click="selectedCategoryId = category.id"
                >
                  {{ category.name }}
                </Badge>
              </div>
            </div>
            <div class="flex items-end">
              <Button class="w-full" type="submit" :disabled="!activeList">Hinzufuegen</Button>
            </div>
          </form>
        </Card>

        <div v-if="activeList" class="space-y-6">
          <Card v-for="group in groupedItems" :key="group.id" class="space-y-4">
            <div class="flex items-center justify-between">
              <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Kategorie</p>
                <h3 class="text-xl font-semibold">{{ group.name }}</h3>
              </div>
              <Badge class="bg-accent/20 text-accent">{{ group.items.length }} Artikel</Badge>
            </div>
            <div class="space-y-2">
              <div
                v-for="item in group.items"
                :key="item.id"
                class="flex items-center justify-between rounded-xl border border-input p-3 transition"
                :class="item.checked ? 'bg-muted/50 text-muted-foreground' : 'bg-card'"
              >
                <button class="flex items-center gap-3 text-left" @click="toggleItem(item.id)">
                  <span
                    class="flex h-5 w-5 items-center justify-center rounded-full border"
                    :class="item.checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input'"
                  >
                    <span v-if="item.checked" class="text-xs">x</span>
                  </span>
                  <span :class="item.checked ? 'line-through' : ''">
                    {{ item.name }}
                  </span>
                </button>
                <Button size="sm" variant="ghost" class="text-muted-foreground" @click="removeItem(item.id)">Entfernen</Button>
              </div>
            </div>
          </Card>
        </div>

        <Card v-else class="text-sm text-muted-foreground">
          Lege zuerst einen Einkaufszettel an, um Artikel hinzuzufuegen.
        </Card>
      </div>
    </div>

    <div v-else class="space-y-6">
      <Card v-if="activeList" class="space-y-6">
        <div class="flex items-center justify-between">
          <div>
            <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Einkaufen</p>
            <h3 class="text-xl font-semibold">Abhakliste</h3>
          </div>
          <Badge class="bg-secondary/20 text-secondary">Nur Abhaken</Badge>
        </div>
        <div v-for="group in groupedItems" :key="group.id" class="space-y-3">
          <div class="flex items-center justify-between">
            <h4 class="text-lg font-semibold">{{ group.name }}</h4>
            <Badge class="bg-accent/20 text-accent">{{ group.items.length }} Artikel</Badge>
          </div>
          <div class="space-y-2">
            <div
              v-for="item in group.items"
              :key="item.id"
              class="flex items-center justify-between rounded-xl border border-input p-3 transition"
              :class="item.checked ? 'bg-muted/50 text-muted-foreground' : 'bg-card'"
            >
              <button class="flex items-center gap-3 text-left" @click="toggleItem(item.id)">
                <span
                  class="flex h-5 w-5 items-center justify-center rounded-full border"
                  :class="item.checked ? 'border-primary bg-primary text-primary-foreground' : 'border-input'"
                >
                  <span v-if="item.checked" class="text-xs">x</span>
                </span>
                <span :class="item.checked ? 'line-through' : ''">
                  {{ item.name }}
                </span>
              </button>
            </div>
          </div>
        </div>
      </Card>
      <Card v-else class="text-sm text-muted-foreground">
        Lege zuerst einen Einkaufszettel an, um Artikel hinzuzufuegen.
      </Card>
    </div>
  </div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Badge from '@/components/ui/Badge.vue'
import { useShoppingStore } from '@/stores/shopping'

const store = useShoppingStore()
const route = useRoute()
const router = useRouter()
const searchTerm = ref('')
const selectedCategoryId = ref('')
const selectedProductId = ref('')
const selectedStoreId = ref('')
const mode = ref<'plan' | 'shop'>('plan')

const routeListId = computed(() => route.params.id as string | undefined)
const activeList = computed(() => store.lists.find((entry) => entry.id === routeListId.value))

const setActiveFromRoute = () => {
  if (activeList.value) {
    store.setActiveList(activeList.value.id)
  }
}

onMounted(() => {
  setActiveFromRoute()
  if (activeList.value?.storeId) {
    selectedStoreId.value = activeList.value.storeId
  }
})

watch(
  () => route.params.id,
  () => {
    setActiveFromRoute()
  }
)

watch(
  () => activeList.value?.storeId,
  (value) => {
    selectedStoreId.value = value || ''
  }
)

const lists = computed(() => store.lists)

const filteredProducts = computed(() => {
  const term = searchTerm.value.trim().toLowerCase()
  if (!term) return []
  return store.products.filter((product) => product.name.toLowerCase().includes(term))
})

const categoryName = (id?: string) => {
  return store.categoryById(id)?.name || ''
}

const formatDate = (value: string) => {
  const date = new Date(value)
  return new Intl.DateTimeFormat('de-DE', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}

const goToList = (id: string) => {
  router.push({ name: 'list', params: { id } })
}

const selectProduct = (productId: string) => {
  const product = store.productById(productId)
  if (!product) return
  selectedProductId.value = productId
  searchTerm.value = product.name
  selectedCategoryId.value = product.categoryId || ''
}

const addItem = () => {
  if (!activeList.value) return
  const name = searchTerm.value.trim()
  if (!name) return

  let productId = selectedProductId.value
  if (!productId) {
    const existing = store.products.find((product) => product.name.toLowerCase() === name.toLowerCase())
    if (existing) {
      productId = existing.id
      selectedCategoryId.value = existing.categoryId || selectedCategoryId.value
    }
  }

  if (!productId) {
    const product = store.addProduct(name, selectedCategoryId.value || undefined)
    productId = product.id
  } else if (selectedCategoryId.value) {
    store.updateProductCategory(productId, selectedCategoryId.value)
  }

  store.addItemToList(activeList.value.id, productId)
  searchTerm.value = ''
  selectedCategoryId.value = ''
  selectedProductId.value = ''
}

const toggleItem = (itemId: string) => {
  if (!activeList.value) return
  store.toggleItem(activeList.value.id, itemId)
}

const removeItem = (itemId: string) => {
  if (!activeList.value) return
  store.removeItem(activeList.value.id, itemId)
}

const setStore = (value: string) => {
  if (!activeList.value) return
  store.setListStore(activeList.value.id, value || undefined)
}

const groupedItems = computed(() => {
  if (!activeList.value) return []
  const list = activeList.value
  const items = list.items.map((item) => {
    const product = store.productById(item.productId)
    return {
      id: item.id,
      name: product?.name || 'Unbekannt',
      categoryId: product?.categoryId,
      checked: item.checked
    }
  })

  const categoryOrder = (() => {
    const storeConfig = store.storeById(list.storeId)
    const ordered = storeConfig?.categoryOrder || []
    const existing = ordered.filter((id) => store.categories.some((cat) => cat.id === id))
    const missing = store.categories.map((cat) => cat.id).filter((id) => !existing.includes(id))
    return [...existing, ...missing]
  })()

  const groups = new Map<string, { id: string; name: string; items: typeof items }>()

  items.forEach((item) => {
    const key = item.categoryId || 'uncat'
    const name = item.categoryId ? categoryName(item.categoryId) : 'Unkategorisiert'
    if (!groups.has(key)) {
      groups.set(key, { id: key, name, items: [] })
    }
    groups.get(key)!.items.push(item)
  })

  const orderedGroups = categoryOrder
    .filter((id) => groups.has(id))
    .map((id) => groups.get(id)!)

  if (groups.has('uncat')) {
    orderedGroups.push(groups.get('uncat')!)
  }

  return orderedGroups.map((group) => ({
    ...group,
    items: [...group.items].sort((a, b) => Number(a.checked) - Number(b.checked))
  }))
})
</script>
