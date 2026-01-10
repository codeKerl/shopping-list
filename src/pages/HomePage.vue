<template>
  <div class="grid gap-6 lg:grid-cols-[1fr_320px]">
    <Card class="space-y-6">
      <div class="flex flex-col gap-4 md:flex-row md:items-center md:justify-between">
        <div>
          <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('home.overview') }}</p>
          <h2 class="text-2xl font-semibold">{{ t('home.allLists') }}</h2>
        </div>
        <Button @click="createNewList">{{ t('home.newList') }}</Button>
      </div>
      <div class="grid gap-4 md:grid-cols-2">
        <div
          v-for="list in lists"
          :key="list.id"
          class="rounded-2xl border border-input bg-card p-4 transition hover:bg-muted"
        >
          <div class="flex items-start justify-between gap-3">
            <RouterLink :to="{ name: 'list', params: { id: list.id } }" class="flex-1">
              <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('home.listLabel') }}</p>
              <p class="text-lg font-semibold">{{ formatDate(list.createdAt) }}</p>
            </RouterLink>
            <div class="flex items-center gap-2">
              <Badge class="bg-accent/20 text-accent">{{ t('home.items', { count: list.items.length }) }}</Badge>
              <button
                class="rounded-full border border-input bg-card/80 p-2 text-muted-foreground transition hover:bg-muted hover:text-foreground"
                type="button"
                :aria-label="t('home.deleteList')"
                @click="removeList(list.id)"
              >
                <TrashIcon class="h-4 w-4" />
              </button>
            </div>
          </div>
        </div>
        <div v-if="lists.length === 0" class="rounded-xl border border-dashed border-input p-4 text-sm text-muted-foreground">
          {{ t('list.empty') }}
        </div>
      </div>
    </Card>

    <Card class="space-y-4">
      <div>
        <p class="text-xs font-semibold uppercase tracking-wide text-muted-foreground">{{ t('home.note') }}</p>
        <h3 class="text-xl font-semibold">{{ t('home.shareTitle') }}</h3>
      </div>
      <p class="text-sm text-muted-foreground">{{ t('home.shareText') }}</p>
      <div class="rounded-xl border border-input bg-muted/40 p-3 text-xs text-muted-foreground">
        {{ t('home.shareExample') }}
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
import { TrashIcon } from '@radix-icons/vue'
import { useI18n } from '@/composables/useI18n'

const store = useShoppingStore()
const router = useRouter()
const { t, locale } = useI18n()

const lists = computed(() => store.lists)

const formatDate = (value: string) => {
  const date = new Date(value)
  return new Intl.DateTimeFormat(locale.value === 'en' ? 'en-US' : 'de-DE', {
    dateStyle: 'medium',
    timeStyle: 'short'
  }).format(date)
}

const createNewList = () => {
  const list = store.createList()
  router.push({ name: 'list', params: { id: list.id } })
}

const removeList = (id: string) => {
  store.removeList(id)
}
</script>
