<script setup lang="ts">
import type { Game } from '@topup/shared-types';
import { Zap, ArrowUpRight, ShieldCheck } from 'lucide-vue-next';

const props = defineProps<{ game: Game }>();
const { locale } = useI18n();

const shortDesc = computed(() =>
  locale.value === 'km' ? props.game.short_description_km : props.game.short_description_en,
);

const artworkMap: Record<string, string> = {
  'mobile-legends': '/images/games/mobile-legends.jpg',
  'free-fire': '/images/games/free-fire.jpg',
  'pubg-mobile': '/images/games/pubg-mobile.jpg',
  'honor-of-kings': '/images/games/honor-of-kings.jpg',
};

const logoMap: Record<string, string> = {
  'mobile-legends': '/images/games/icons/mobile-legends-logo.jpg',
  'free-fire': '/images/games/icons/free-fire-logo.jpg',
  'pubg-mobile': '/images/games/icons/pubg-mobile-logo.jpg',
  'honor-of-kings': '/images/games/icons/honor-of-kings-logo.jpg',
};

const coverImage = computed(() => artworkMap[props.game.slug] || props.game.thumbnail_url || '/images/games/mlbb-thumb.svg');
const gameLogo = computed(() => logoMap[props.game.slug] || coverImage.value);
</script>

<template>
  <NuxtLink
    :to="`/games/${game.slug}`"
    class="group relative glass-card glass-card-hover rounded-2xl overflow-hidden flex flex-col"
  >
    <div class="relative aspect-[16/10] overflow-hidden bg-brand-surface">
      <img
        :src="coverImage"
        :alt="game.name"
        class="h-full w-full object-cover transition-transform duration-500 ease-out group-hover:scale-110"
        loading="lazy"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-[#0B1020] via-[#0B1020]/30 to-transparent" />

      <span
        v-if="game.is_in_maintenance"
        class="absolute top-3 right-3 rounded-full bg-amber-500 px-2.5 py-1 text-[11px] font-bold text-slate-950 shadow-lg"
      >
        {{ $t('game.maintenance') }}
      </span>
      <span
        v-else
        class="absolute top-3 left-3 inline-flex items-center gap-1 rounded-full bg-black/55 backdrop-blur-md px-2.5 py-1 text-[11px] font-semibold text-sky-300 border border-sky-400/30"
      >
        <Zap class="h-3 w-3 text-amber-400 fill-amber-400" />
        {{ locale === 'km' ? 'បញ្ចូលភ្លាមៗ' : 'Instant' }}
      </span>

      <div class="absolute bottom-2.5 left-2.5 right-2.5 sm:bottom-3 sm:left-3 sm:right-3 flex items-end gap-2 sm:gap-3">
        <img
          :src="gameLogo"
          :alt="`${game.name} Logo`"
          class="h-10 w-10 sm:h-14 sm:w-14 rounded-xl sm:rounded-2xl object-cover border-2 border-white/25 shadow-xl shrink-0 transition-transform duration-300 group-hover:scale-105"
          loading="lazy"
        />
        <div class="min-w-0">
          <span class="hidden sm:inline-block rounded-md bg-white/15 backdrop-blur-md px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-slate-100">
            {{ game.publisher }}
          </span>
          <h3 class="font-bold text-xs sm:text-base text-white leading-snug mt-0.5 drop-shadow truncate">
            {{ game.name }}
          </h3>
        </div>
      </div>
    </div>

    <div class="p-3 sm:p-4 flex-1 flex flex-col justify-between gap-2.5 sm:gap-3">
      <p class="hidden sm:block text-xs text-brand-muted line-clamp-2 leading-relaxed">{{ shortDesc }}</p>
      <div class="sm:pt-2 sm:border-t border-white/5 flex items-center justify-between gap-1">
        <span class="inline-flex items-center gap-1 text-[10px] sm:text-[11px] text-emerald-400 font-medium">
          <ShieldCheck class="h-3 w-3 sm:h-3.5 sm:w-3.5 shrink-0" />
          <span class="truncate">{{ locale === 'km' ? 'គ្មានពាក្យសម្ងាត់' : 'ID Only' }}</span>
        </span>
        <span class="inline-flex items-center gap-1 rounded-lg bg-brand-primary/20 group-hover:bg-brand-primary px-2.5 py-1 sm:px-3 sm:py-1.5 text-[11px] sm:text-xs font-bold text-sky-300 group-hover:text-white transition-colors shrink-0">
          <span>{{ locale === 'km' ? 'បញ្ចូល' : 'Top Up' }}</span>
          <ArrowUpRight class="h-3 w-3 sm:h-3.5 sm:w-3.5" />
        </span>
      </div>
    </div>
  </NuxtLink>
</template>
