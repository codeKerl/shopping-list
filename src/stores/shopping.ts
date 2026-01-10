import { defineStore } from 'pinia'

const STORAGE_KEY = 'app-state'
const SYNC_QUEUE_KEY = 'sync-queue'
const LAST_SYNC_KEY = 'last-synced-at'

export interface Category {
  id: string
  name: string
}

export interface Unit {
  id: string
  name: string
}

export interface Product {
  id: string
  name: string
  categoryId?: string
}

export interface StoreConfig {
  id: string
  name: string
  categoryOrder: string[]
}

export interface ListItem {
  id: string
  productId: string
  checked: boolean
  quantity: number
  note?: string
}

export interface ShoppingList {
  id: string
  createdAt: string
  storeId?: string
  items: ListItem[]
}

export interface ShoppingState {
  categories: Category[]
  units: Unit[]
  products: Product[]
  stores: StoreConfig[]
  lists: ShoppingList[]
  activeListId?: string
  revision?: string
  syncQueueCount: number
  locale: 'de' | 'en' | 'fr' | 'es'
}

const uid = () => Math.random().toString(36).slice(2, 10)

const createEvent = (type: string, payload: Record<string, unknown>): SyncEvent => ({
  eventId: uid(),
  timestamp: new Date().toISOString(),
  type,
  payload
})

const loadState = (): ShoppingState => {
  const raw = localStorage.getItem(STORAGE_KEY)
  if (!raw) {
    return {
      categories: [],
      units: [],
      products: [],
      stores: [],
      lists: [],
      activeListId: undefined,
      revision: undefined,
      syncQueueCount: loadQueue().length,
      locale: 'de'
    }
  }
  try {
    const parsed = JSON.parse(raw) as ShoppingState
    return {
      ...parsed,
      units: parsed.units || [],
      syncQueueCount: loadQueue().length,
      locale: parsed.locale || 'de'
    }
  } catch {
    return {
      categories: [],
      units: [],
      products: [],
      stores: [],
      lists: [],
      activeListId: undefined,
      revision: undefined,
      syncQueueCount: loadQueue().length,
      locale: 'de'
    }
  }
}

const loadQueue = (): SyncEvent[] => {
  const raw = localStorage.getItem(SYNC_QUEUE_KEY)
  if (!raw) return []
  try {
    return JSON.parse(raw) as SyncEvent[]
  } catch {
    return []
  }
}

const persistQueue = (queue: SyncEvent[]) => {
  localStorage.setItem(SYNC_QUEUE_KEY, JSON.stringify(queue))
}

export interface SyncEvent {
  eventId: string
  timestamp: string
  type: string
  payload: Record<string, unknown>
}

export const useShoppingStore = defineStore('shopping', {
  state: (): ShoppingState => loadState(),
  getters: {
    activeList(state): ShoppingList | undefined {
      return state.lists.find((list) => list.id === state.activeListId)
    },
    storeById: (state) => {
      return (id?: string) => state.stores.find((store) => store.id === id)
    },
    categoryById: (state) => {
      return (id?: string) => state.categories.find((cat) => cat.id === id)
    },
    productById: (state) => {
      return (id: string) => state.products.find((prod) => prod.id === id)
    }
  },
  actions: {
    persist() {
      localStorage.setItem(STORAGE_KEY, JSON.stringify(this.$state))
    },
    enqueueEvent(type: string, payload: Record<string, unknown>) {
      const queue = loadQueue()
      queue.push(createEvent(type, payload))
      persistQueue(queue)
      this.syncQueueCount = queue.length
    },
    replaceState(state: ShoppingState) {
      this.$state = {
        ...state,
        units: state.units || [],
        locale: state.locale || 'de',
        syncQueueCount: loadQueue().length
      }
      this.persist()
    },
    setLocale(locale: 'de' | 'en' | 'fr' | 'es') {
      this.locale = locale
      this.persist()
      this.enqueueEvent('settings:locale', { locale })
    },
    setRevision(revision?: string) {
      this.revision = revision
      this.persist()
    },
    markSynced() {
      localStorage.setItem(LAST_SYNC_KEY, new Date().toISOString())
    },
    clearQueue() {
      persistQueue([])
      this.syncQueueCount = 0
    },
    getQueue() {
      return loadQueue()
    },
    createList() {
      const list: ShoppingList = {
        id: uid(),
        createdAt: new Date().toISOString(),
        items: []
      }
      this.lists.unshift(list)
      this.activeListId = list.id
      this.persist()
      this.enqueueEvent('list:create', { list })
      return list
    },
    setActiveList(id: string) {
      this.activeListId = id
      this.persist()
    },
    addCategory(name: string) {
      const category: Category = { id: uid(), name }
      this.categories.push(category)
      this.persist()
      this.enqueueEvent('category:create', { category })
      return category
    },
    addUnit(name: string) {
      const unit: Unit = { id: uid(), name }
      this.units.push(unit)
      this.persist()
      this.enqueueEvent('unit:create', { unit })
      return unit
    },
    updateUnit(unitId: string, name: string) {
      const unit = this.units.find((item) => item.id === unitId)
      if (!unit) return
      unit.name = name
      this.persist()
      this.enqueueEvent('unit:update', { unitId, name })
    },
    removeUnit(unitId: string) {
      this.units = this.units.filter((unit) => unit.id !== unitId)
      this.persist()
      this.enqueueEvent('unit:remove', { unitId })
    },
    addStore(name: string) {
      const store: StoreConfig = { id: uid(), name, categoryOrder: [] }
      this.stores.push(store)
      this.persist()
      this.enqueueEvent('store:create', { store })
      return store
    },
    setStoreOrder(storeId: string, categoryIds: string[]) {
      const store = this.stores.find((item) => item.id === storeId)
      if (!store) return
      store.categoryOrder = [...categoryIds]
      this.persist()
      this.enqueueEvent('store:order', { storeId, categoryIds })
    },
    addProduct(name: string, categoryId?: string) {
      const product: Product = { id: uid(), name, categoryId }
      this.products.push(product)
      this.persist()
      this.enqueueEvent('product:create', { product })
      return product
    },
    updateProductCategory(productId: string, categoryId?: string) {
      const product = this.products.find((item) => item.id === productId)
      if (!product) return
      product.categoryId = categoryId
      this.persist()
      this.enqueueEvent('product:update', { productId, categoryId })
    },
    addItemToList(listId: string, productId: string, note?: string) {
      const list = this.lists.find((item) => item.id === listId)
      if (!list) return
      const product = this.products.find((item) => item.id === productId)
      const listItem = { id: uid(), productId, checked: false, quantity: 1, note }
      list.items.push(listItem)
      this.persist()
      this.enqueueEvent('list:item:add', { listId, item: listItem, productName: product?.name || '' })
    },
    toggleItem(listId: string, itemId: string) {
      const list = this.lists.find((item) => item.id === listId)
      if (!list) return
      const item = list.items.find((entry) => entry.id === itemId)
      if (!item) return
      item.checked = !item.checked
      this.persist()
      this.enqueueEvent('list:item:toggle', { listId, itemId, checked: item.checked })
    },
    setListStore(listId: string, storeId?: string) {
      const list = this.lists.find((item) => item.id === listId)
      if (!list) return
      list.storeId = storeId || undefined
      this.persist()
      this.enqueueEvent('list:store:set', { listId, storeId: list.storeId })
    },
    removeItem(listId: string, itemId: string) {
      const list = this.lists.find((item) => item.id === listId)
      if (!list) return
      list.items = list.items.filter((entry) => entry.id !== itemId)
      this.persist()
      this.enqueueEvent('list:item:remove', { listId, itemId })
    },
    updateItemQuantity(listId: string, itemId: string, quantity: number) {
      const list = this.lists.find((item) => item.id === listId)
      if (!list) return
      const item = list.items.find((entry) => entry.id === itemId)
      if (!item) return
      const safeQuantity = Math.max(1, Math.floor(quantity))
      item.quantity = safeQuantity
      this.persist()
      this.enqueueEvent('list:item:quantity', { listId, itemId, quantity: safeQuantity })
    },
    removeList(listId: string) {
      this.lists = this.lists.filter((list) => list.id !== listId)
      if (this.activeListId === listId) {
        this.activeListId = this.lists[0]?.id
      }
      this.persist()
      this.enqueueEvent('list:remove', { listId })
    },
    removeProduct(productId: string) {
      this.products = this.products.filter((product) => product.id !== productId)
      this.lists = this.lists.map((list) => ({
        ...list,
        items: list.items.filter((item) => item.productId !== productId)
      }))
      this.persist()
      this.enqueueEvent('product:remove', { productId })
    }
  }
})
