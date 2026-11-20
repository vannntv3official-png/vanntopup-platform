<script setup lang="ts">
import type { Game } from '@topup/shared-types';
import { Search } from 'lucide-vue-next';

const { request } = useApi();
const { locale } = useI18n();
const search = ref('');
const selectedCategory = ref('');

const { data: categories } = await useAsyncData('categories', async () => {
  const res = await request<{ data: Array<{ slug: string; name_en: string; name_km: string }> }>('/categories');
  return res.data;
});

const { data: games, pending } = await useAsyncData(
  'catalog-games',
  async () => {
    const params = new URLSearchParams();
    if (selectedCategory.value) params.set('category', selectedCategory.value);
    if (search.value.trim()) params.set('q', search.value.trim());
    const qs = params.toString() ? `?${params.toString()}` : '';
    const res = await request<{ data: Game[] }>(`/games${qs}`);
    return res.data;
  },
  { watch: [selectedCategory, search] },
);
</script>

<template>
  <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-10 space-y-8">
    <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ $t('sections.allGames') }}</h1>

      <div class="relative w-full sm:w-80">
        <Search class="h-4 w-4 text-brand-muted absolute left-3.5 top-1/2 -translate-y-1/2" />
        <input
          v-model="search"
          type="search"
          :placeholder="$t('hero.searchPlaceholder')"
          class="w-full rounded-xl border border-brand-border bg-brand-surface pl-10 pr-4 py-2.5 text-sm text-white placeholder-brand-muted focus:border-brand-accent focus:outline-none"
        />
      </div>
    </div>

    <div class="flex flex-wrap gap-2">
      <button
        type="button"
        class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
        :class="selectedCategory === '' ? 'bg-brand-primary text-white' : 'bg-brand-surface text-brand-muted hover:text-white border border-brand-border'"
        @click="selectedCategory = ''"
      >
        All
      </button>
      <button
        v-for="cat in categories || []"
        :key="cat.slug"
        type="button"
        class="px-3.5 py-1.5 rounded-full text-xs font-semibold transition-colors"
        :class="selectedCategory === cat.slug ? 'bg-brand-primary text-white' : 'bg-brand-surface text-brand-muted hover:text-white border border-brand-border'"
        @click="selectedCategory = cat.slug"
      >
        {{ locale === 'km' ? cat.name_km : cat.name_en }}
      </button>
    </div>

    <div v-if="pending" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <div v-for="i in 4" :key="i" class="h-72 rounded-2xl bg-brand-surface animate-pulse" />
    </div>
    <div v-else class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5">
      <GameCard v-for="game in games || []" :key="game.id" :game="game" />
    </div>
  </div>
</template>
