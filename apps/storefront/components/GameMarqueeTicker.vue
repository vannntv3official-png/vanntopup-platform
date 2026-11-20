<script setup lang="ts">
import { Zap } from 'lucide-vue-next';

const { locale } = useI18n();

const tickerItems = computed(() => [
  {
    slug: 'mobile-legends',
    name: 'Mobile Legends KH',
    region: 'KH',
    currency: 'Diamonds',
    speed: locale.value === 'km' ? '3s ភ្លាមៗ' : '3s Instant',
    image: '/images/games/icons/mobile-legends-logo.jpg',
  },
  {
    slug: 'free-fire',
    name: 'Garena Free Fire',
    region: 'SEA',
    currency: 'Diamonds',
    speed: locale.value === 'km' ? '3s ភ្លាមៗ' : '3s Instant',
    image: '/images/games/icons/free-fire-logo.jpg',
  },
  {
    slug: 'pubg-mobile',
    name: 'PUBG Mobile',
    region: 'GLOBAL',
    currency: 'UC',
    speed: locale.value === 'km' ? '5s ភ្លាមៗ' : '5s Instant',
    image: '/images/games/icons/pubg-mobile-logo.jpg',
  },
  {
    slug: 'honor-of-kings',
    name: 'Honor of Kings',
    region: 'SEA',
    currency: 'Tokens',
    speed: locale.value === 'km' ? '3s ភ្លាមៗ' : '3s Instant',
    image: '/images/games/icons/honor-of-kings-logo.jpg',
  },
  {
    slug: 'mobile-legends',
    name: 'MLBB Weekly Pass',
    region: 'KH',
    currency: 'Pass',
    speed: locale.value === 'km' ? '3s ភ្លាមៗ' : '3s Instant',
    image: '/images/games/icons/mobile-legends-logo.jpg',
  },
]);

// Duplicate array seamlessly for infinite marquee loop
const loopedItems = computed(() => [...tickerItems.value, ...tickerItems.value]);
</script>

<template>
  <div class="marquee-wrapper marquee-mask overflow-hidden py-2">
    <div class="animate-marquee gap-3.5 pr-3.5">
      <NuxtLink
        v-for="(item, idx) in loopedItems"
        :key="`${item.slug}-${idx}`"
        :to="`/games/${item.slug}`"
        class="group flex items-center gap-3 rounded-2xl border border-white/10 bg-[#11192E]/90 hover:bg-[#172340] hover:border-sky-400/50 px-3.5 py-2.5 shadow-lg transition-all duration-200 hover:-translate-y-0.5 shrink-0"
      >
        <img
          :src="item.image"
          :alt="item.name"
          class="h-11 w-11 rounded-xl object-cover border border-white/15 shrink-0 transition-transform duration-300 group-hover:scale-105"
        />
        <div class="space-y-1">
          <div class="flex items-center gap-1.5">
            <span class="text-xs sm:text-sm font-bold text-white whitespace-nowrap group-hover:text-sky-300 transition-colors">
              {{ item.name }}
            </span>
            <span class="text-[10px] font-extrabold text-slate-400 uppercase">{{ item.region }}</span>
          </div>
          <div class="flex items-center gap-2">
            <span class="text-[11px] font-bold text-sky-400">{{ item.currency }}</span>
            <span class="inline-flex items-center gap-0.5 rounded-full bg-emerald-500/15 border border-emerald-400/30 px-2 py-0.5 text-[10px] font-bold text-emerald-300 whitespace-nowrap">
              <Zap class="h-2.5 w-2.5 fill-emerald-400 text-emerald-400" />
              {{ item.speed }}
            </span>
          </div>
        </div>
      </NuxtLink>
    </div>
  </div>
</template>
