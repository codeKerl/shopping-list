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
          <RouterLink
            class="inline-flex items-center justify-center rounded-full border border-input bg-transparent px-3 py-1 text-xs font-semibold uppercase tracking-wide text-primary transition hover:bg-muted"
            to="/"
          >
            Zur Uebersicht
          </RouterLink>
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

    <div class="space-y-6" v-if="mode === 'plan'">
        <Card class="space-y-6">
          <form class="grid gap-4 md:grid-cols-[1.4fr_0.6fr_0.6fr_0.8fr_auto]" @submit.prevent="addItem">
            <div class="space-y-2">
              <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Lebensmittel</label>
              <Input v-model="productName" placeholder="z.B. kleine Tomaten" @focus="selectedProductId = ''" />
              <div v-if="productName" class="rounded-xl border border-input bg-card p-2 text-sm">
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
              <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Menge</label>
              <Input v-model="productAmount" placeholder="z.B. 500" />
            </div>
            <div class="space-y-2">
              <label class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">Einheit</label>
              <Select v-model="selectedUnitId">
                <option value="">Ohne</option>
                <option v-for="unit in store.units" :key="unit.id" :value="unit.id">
                  {{ unit.name }}
                </option>
              </Select>
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
                  {{ formatItemName(item) }}
                </span>
              </button>
              <div class="flex items-center gap-2">
                <button
                  class="rounded-full border border-input bg-card/80 p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                  type="button"
                  aria-label="Menge reduzieren"
                  @click="updateQuantity(item.id, item.quantity - 1)"
                >
                  <MinusIcon class="h-4 w-4" />
                </button>
                <span class="text-xs font-semibold">{{ item.quantity }}x</span>
                <button
                  class="rounded-full border border-input bg-card/80 p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                  type="button"
                  aria-label="Menge erhoehen"
                  @click="updateQuantity(item.id, item.quantity + 1)"
                >
                  <PlusIcon class="h-4 w-4" />
                </button>
                <Button size="sm" variant="ghost" class="text-muted-foreground" @click="removeItem(item.id)">Entfernen</Button>
              </div>
            </div>
            </div>
          </Card>
        </div>

        <Card v-else class="text-sm text-muted-foreground">
          Lege zuerst einen Einkaufszettel an, um Artikel hinzuzufuegen.
        </Card>
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
                  {{ formatItemName(item) }}
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
import { useRoute } from 'vue-router'
import Card from '@/components/ui/Card.vue'
import Button from '@/components/ui/Button.vue'
import Input from '@/components/ui/Input.vue'
import Select from '@/components/ui/Select.vue'
import Badge from '@/components/ui/Badge.vue'
import { useShoppingStore } from '@/stores/shopping'
import { MinusIcon, PlusIcon } from '@radix-icons/vue'

const store = useShoppingStore()
const route = useRoute()
const productName = ref('')
const productAmount = ref('')
const selectedCategoryId = ref('')
const selectedProductId = ref('')
const selectedStoreId = ref('')
const selectedUnitId = ref('')
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


const filteredProducts = computed(() => {
  const term = productName.value.trim().toLowerCase()
  if (!term) return []
  return store.products.filter((product) => product.name.toLowerCase().includes(term))
})

const categoryName = (id?: string) => {
  return store.categoryById(id)?.name || ''
}

const unitNameById = (id?: string) => {
  return store.units.find((unit) => unit.id === id)?.name || ''
}

const buildProductName = () => {
  const base = productName.value.trim()
  if (!base) return ''
  const amount = productAmount.value.trim()
  const unitName = unitNameById(selectedUnitId.value)
  if (amount && unitName) {
    return `${base} (${amount}${unitName})`
  }
  if (amount) {
    return `${base} (${amount})`
  }
  return base
}

const parseProductName = (value: string) => {
  const match = value.match(/^(.*?)\s*\(([^)]+)\)\s*$/)
  if (!match) {
    return { name: value, amount: '', unitId: '' }
  }
  const name = match[1].trim()
  const amountUnit = match[2].trim()
  const amountMatch = amountUnit.match(/^([0-9]+(?:[.,][0-9]+)?)\s*([a-zA-Zµ]+)?$/)
  if (!amountMatch) {
    return { name, amount: amountUnit, unitId: '' }
  }
  const amount = amountMatch[1].replace(',', '.')
  const unitName = amountMatch[2] || ''
  const unit = store.units.find((item) => item.name.toLowerCase() === unitName.toLowerCase())
  return { name, amount, unitId: unit?.id || '' }
}

const formatDate = (value: string) => {
  const date = new Date(value)
  return new Intl.DateTimeFormat('de-DE', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}


const selectProduct = (productId: string) => {
  const product = store.productById(productId)
  if (!product) return
  selectedProductId.value = productId
  const parsed = parseProductName(product.name)
  productName.value = parsed.name
  productAmount.value = parsed.amount
  selectedUnitId.value = parsed.unitId
  selectedCategoryId.value = product.categoryId || ''
}

const addItem = () => {
  if (!activeList.value) return
  const name = buildProductName()
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
  productName.value = ''
  productAmount.value = ''
  selectedUnitId.value = ''
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
      checked: item.checked,
      quantity: item.quantity ?? 1
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

const formatItemName = (item: { name: string; quantity: number }) => {
  return item.quantity > 1 ? `${item.quantity}x ${item.name}` : item.name
}

const updateQuantity = (itemId: string, quantity: number) => {
  if (!activeList.value) return
  store.updateItemQuantity(activeList.value.id, itemId, quantity)
}
</script>
