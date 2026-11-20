<script setup lang="ts">
import type { Game } from '@topup/shared-types';
import { Search, ShieldCheck, Zap, QrCode, Headphones, Clock, Lock, ArrowRight, Flame } from 'lucide-vue-next';
import { usePreferencesStore } from '~/stores/preferences';

const { request } = useApi();
const { locale } = useI18n();
const prefs = usePreferencesStore();

const searchQuery = ref('');
const selectedCategory = ref('');

const { data: categories } = await useAsyncData('home-categories', async () => {
  const res = await request<{ data: Array<{ slug: string; name_en: string; name_km: string }> }>('/categories');
  return res.data;
});

const { data: gamesResponse, pending } = await useAsyncData('home-games', async () => {
  const res = await request<{ data: Game[]; meta?: { exchange_rate_usd_to_khr?: number } }>('/games');
  if (res.meta?.exchange_rate_usd_to_khr) {
    prefs.setExchangeRate(res.meta.exchange_rate_usd_to_khr);
  }
  return res.data;
});

const filteredGames = computed(() => {
  const all = gamesResponse.value || [];
  const q = searchQuery.value.trim().toLowerCase();
  return all.filter((g) => {
    const matchesCat = !selectedCategory.value || g.category_slug === selectedCategory.value;
    const matchesQuery = !q || g.name.toLowerCase().includes(q) || g.publisher.toLowerCase().includes(q);
    return matchesCat && matchesQuery;
  });
});
</script>

<template>
  <div>
    <!-- Top Section: Full-Width Banner Carousel + Infinite Game Marquee + Trust Strip -->
    <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 space-y-5">
      <div class="animate-fade-up">
        <HeroBannerCarousel />
      </div>

      <!-- Smooth Infinite Scrolling Game Pill Marquee -->
      <div class="animate-fade-up" style="animation-delay: 80ms">
        <GameMarqueeTicker />
      </div>

      <!-- Trust & KHQR Guarantee Strip -->
      <div class="grid grid-cols-2 lg:grid-cols-4 gap-3 sm:gap-4 animate-fade-up" style="animation-delay: 140ms">
        <div class="glass-card rounded-2xl p-3.5 sm:p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-blue-500/15 border border-blue-400/30 flex items-center justify-center shrink-0">
            <Zap class="h-5 w-5 text-sky-400" />
          </div>
          <div>
            <p class="text-xs sm:text-sm font-bold text-white">{{ locale === 'km' ? 'បញ្ចូលក្នុង ៣ វិនាទី' : '3-Second Delivery' }}</p>
            <p class="text-[11px] text-slate-400">{{ locale === 'km' ? 'ស្វ័យប្រវត្តិ ២៤ ម៉ោង' : '24/7 Automated' }}</p>
          </div>
        </div>
        <div class="glass-card rounded-2xl p-3.5 sm:p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-red-500/15 border border-red-400/30 flex items-center justify-center shrink-0">
            <QrCode class="h-5 w-5 text-red-400" />
          </div>
          <div>
            <p class="text-xs sm:text-sm font-bold text-white">{{ locale === 'km' ? 'Bakong KHQR & ABA' : 'Bakong KHQR' }}</p>
            <p class="text-[11px] text-slate-400">USD & KHR ៛</p>
          </div>
        </div>
        <div class="glass-card rounded-2xl p-3.5 sm:p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-emerald-500/15 border border-emerald-400/30 flex items-center justify-center shrink-0">
            <Lock class="h-5 w-5 text-emerald-400" />
          </div>
          <div>
            <p class="text-xs sm:text-sm font-bold text-white">{{ locale === 'km' ? 'សុវត្ថិភាព ១០០%' : '100% Safe & Legal' }}</p>
            <p class="text-[11px] text-slate-400">{{ locale === 'km' ? 'មិនត្រូវការពាក្យសម្ងាត់' : 'ID Only — No Password' }}</p>
          </div>
        </div>
        <div class="glass-card rounded-2xl p-3.5 sm:p-4 flex items-center gap-3">
          <div class="h-10 w-10 rounded-xl bg-amber-500/15 border border-amber-400/30 flex items-center justify-center shrink-0">
            <Headphones class="h-5 w-5 text-amber-400" />
          </div>
          <div>
            <p class="text-xs sm:text-sm font-bold text-white">{{ locale === 'km' ? 'Telegram ជំនួយ ២៤/៧' : '24/7 Telegram Care' }}</p>
            <p class="text-[11px] text-slate-400">{{ locale === 'km' ? 'ឆ្លើយតបរហ័សនៅកម្ពុជា' : 'Local Cambodia Support' }}</p>
          </div>
        </div>
      </div>
    </div>

    <!-- Main Game Catalog Section (Hariki/Codashop Style Instant Access) -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-10 pb-6 space-y-6">
      <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
        <div>
          <span class="inline-flex items-center gap-1.5 text-xs font-bold uppercase tracking-widest text-sky-400">
            <Flame class="h-3.5 w-3.5 text-amber-400" />
            {{ locale === 'km' ? 'ជ្រើសរើសហ្គេមដើម្បីបញ្ចូល' : 'INSTANT TOP-UP CATALOG' }}
          </span>
          <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-0.5">{{ $t('sections.featuredGames') }}</h2>
        </div>

        <!-- Search & Track Shortcut -->
        <div class="flex flex-col sm:flex-row gap-2.5 w-full md:w-auto">
          <div class="relative flex-1 md:w-72">
            <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input
              v-model="searchQuery"
              type="search"
              :placeholder="$t('hero.searchPlaceholder')"
              class="w-full rounded-xl border border-white/15 bg-[#0D1528] pl-10 pr-4 py-2.5 text-xs sm:text-sm text-white placeholder-slate-400 focus:border-sky-400 focus:outline-none"
            />
          </div>
          <NuxtLink
            to="/track"
            class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 hover:border-sky-400/50 px-4 py-2.5 text-xs font-bold text-slate-200 transition-colors shrink-0"
          >
            <span>{{ $t('nav.track') }}</span>
            <ArrowRight class="h-3.5 w-3.5 text-sky-400" />
          </NuxtLink>
        </div>
      </div>

      <!-- Category Filter Pills -->
      <div class="flex flex-wrap items-center gap-2">
        <button
          type="button"
          class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
          :class="selectedCategory === ''
            ? 'btn-primary-glow text-white'
            : 'border border-white/10 bg-white/5 text-slate-300 hover:text-white hover:bg-white/10'"
          @click="selectedCategory = ''"
        >
          {{ locale === 'km' ? 'ហ្គេមទាំងអស់' : 'All Games' }}
        </button>
        <button
          v-for="cat in categories || []"
          :key="cat.slug"
          type="button"
          class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
          :class="selectedCategory === cat.slug
            ? 'btn-primary-glow text-white'
            : 'border border-white/10 bg-white/5 text-slate-300 hover:text-white hover:bg-white/10'"
          @click="selectedCategory = cat.slug"
        >
          {{ locale === 'km' ? cat.name_km : cat.name_en }}
        </button>
      </div>

      <!-- Responsive Game Grid (2-col on mobile, 4-col on desktop) -->
      <div v-if="pending" class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div v-for="i in 4" :key="i" class="h-64 sm:h-72 rounded-2xl bg-brand-surface animate-pulse" />
      </div>

      <div v-else-if="filteredGames.length === 0" class="glass-card rounded-2xl p-10 text-center text-sm text-slate-400">
        {{ locale === 'km' ? 'រកមិនឃើញហ្គេមដែលអ្នកស្វែងរកទេ។' : 'No matching games found.' }}
      </div>

      <div v-else class="grid grid-cols-2 lg:grid-cols-4 gap-4 sm:gap-6">
        <div
          v-for="(game, idx) in filteredGames"
          :key="game.id"
          class="animate-fade-up"
          :style="{ animationDelay: `${idx * 70}ms` }"
        >
          <GameCard :game="game" />
        </div>
      </div>
    </section>

    <!-- Reseller & Wholesale Section -->
    <ResellerSection />

    <!-- Modern 4-Step How It Works -->
    <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-8">
      <div class="mb-7">
        <p class="text-xs font-bold uppercase tracking-widest text-sky-400">Fast & Simple</p>
        <h2 class="text-2xl sm:text-3xl font-extrabold text-white mt-1">{{ $t('sections.howItWorks') }}</h2>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div
          v-for="(step, idx) in [
            { num: '01', icon: Zap, title: $t('sections.step1Title'), desc: $t('sections.step1Desc') },
            { num: '02', icon: ShieldCheck, title: $t('sections.step2Title'), desc: $t('sections.step2Desc') },
            { num: '03', icon: QrCode, title: $t('sections.step3Title'), desc: $t('sections.step3Desc') },
            { num: '04', icon: Clock, title: $t('sections.step4Title'), desc: $t('sections.step4Desc') },
          ]"
          :key="idx"
          class="glass-card rounded-2xl p-5 space-y-2.5 relative overflow-hidden"
        >
          <span class="absolute top-3 right-4 text-4xl font-black text-white/[0.04] select-none">{{ step.num }}</span>
          <div class="h-10 w-10 rounded-xl bg-gradient-to-br from-blue-600/30 to-sky-400/20 border border-sky-400/30 flex items-center justify-center">
            <component :is="step.icon" class="h-5 w-5 text-sky-400" />
          </div>
          <h3 class="font-bold text-sm sm:text-base text-white">{{ step.title }}</h3>
          <p class="text-xs text-slate-400 leading-relaxed">{{ step.desc }}</p>
        </div>
      </div>
    </section>
  </div>
</template>
