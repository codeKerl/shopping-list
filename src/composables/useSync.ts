import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import type { ShoppingState } from '@/stores/shopping'
import { useShoppingStore } from '@/stores/shopping'

const SYNC_ENDPOINT = '/api/sync.php'
const STATE_ENDPOINT = '/api/state.php'
const LAST_SYNC_KEY = 'last-synced-at'

export function useSync() {
  const store = useShoppingStore()
  const isOnline = ref(navigator.onLine)
  const isSyncing = ref(false)
  const lastSyncedAt = ref(localStorage.getItem(LAST_SYNC_KEY) || '')
  const error = ref('')

  const queueCount = computed(() => store.syncQueueCount)

  const updateOnline = () => {
    isOnline.value = navigator.onLine
  }

  const markSynced = () => {
    const now = new Date().toISOString()
    localStorage.setItem(LAST_SYNC_KEY, now)
    lastSyncedAt.value = now
  }

  const sendEvents = async () => {
    const queue = store.getQueue()
    if (queue.length === 0) return true
    const headers: Record<string, string> = {
      'Content-Type': 'application/json'
    }
    if (import.meta.env.VITE_API_KEY) {
      headers.Authorization = `Bearer ${import.meta.env.VITE_API_KEY}`
    }
    const response = await fetch(SYNC_ENDPOINT, {
      method: 'POST',
      headers,
      body: JSON.stringify({ events: queue })
    })
    if (!response.ok) {
      throw new Error(`Sync failed with ${response.status}`)
    }
    store.clearQueue()
    markSynced()
    return true
  }

  const pullState = async () => {
    const response = await fetch(STATE_ENDPOINT)
    if (!response.ok) {
      throw new Error(`State pull failed with ${response.status}`)
    }
    const data = (await response.json()) as { state: ShoppingState; revision?: string }
    if (data?.state) {
      store.replaceState({
        ...data.state,
        revision: data.revision
      })
    }
  }

  const syncNow = async () => {
    if (!isOnline.value || isSyncing.value) return
    isSyncing.value = true
    error.value = ''
    try {
      await sendEvents()
      await pullState()
    } catch (err) {
      error.value = err instanceof Error ? err.message : 'Sync failed'
    } finally {
      isSyncing.value = false
    }
  }

  let intervalId: number | null = null

  const startPolling = () => {
    if (intervalId) return
    intervalId = window.setInterval(() => {
      if (document.visibilityState === 'visible') {
        syncNow()
      }
    }, 60000)
  }

  const stopPolling = () => {
    if (intervalId) {
      window.clearInterval(intervalId)
      intervalId = null
    }
  }

  const handleVisibility = () => {
    if (document.visibilityState === 'visible') {
      syncNow()
    }
  }

  onMounted(() => {
    window.addEventListener('online', updateOnline)
    window.addEventListener('offline', updateOnline)
    window.addEventListener('focus', syncNow)
    document.addEventListener('visibilitychange', handleVisibility)
    startPolling()
    if (isOnline.value) {
      syncNow()
    }
  })

  onBeforeUnmount(() => {
    window.removeEventListener('online', updateOnline)
    window.removeEventListener('offline', updateOnline)
    window.removeEventListener('focus', syncNow)
    document.removeEventListener('visibilitychange', handleVisibility)
    stopPolling()
  })

  return {
    isOnline,
    isSyncing,
    lastSyncedAt,
    error,
    queueCount,
    syncNow
  }
}
