<script setup lang="ts">
import { ChevronLeft, ChevronRight, Sparkles, ArrowRight, Copy, Check, Zap } from 'lucide-vue-next';

const { locale } = useI18n();
const toast = useToast();

const activeIndex = ref(0);
const isPaused = ref(false);
const copied = ref(false);

const slides = computed(() => [
  {
    id: 'mlbb',
    badge: locale.value === 'km' ? '🔥 ពេញនិយមបំផុតនៅកម្ពុជា' : '🔥 #1 Popular in Cambodia',
    title: locale.value === 'km'
      ? 'Mobile Legends: Bang Bang — បញ្ចូលពេជ្រភ្លាមៗ ២៤/៧'
      : 'Mobile Legends: Bang Bang — Instant Diamonds 24/7',
    subtitle: locale.value === 'km'
      ? 'Weekly Diamond Pass ចាប់ពី $1.90 • ទូទាត់តាម Bakong KHQR ដោយស្វ័យប្រវត្តិ មិនចាំបាច់ប្រើពាក្យសម្ងាត់។'
      : 'Weekly Diamond Pass from $1.90 • Automated Bakong KHQR checkout with zero password required.',
    image: '/images/games/mobile-legends.jpg',
    accent: 'from-blue-950/95 via-[#091126]/85 to-sky-900/35',
    ctaLabel: locale.value === 'km' ? 'បញ្ចូល MLBB ឥឡូវនេះ' : 'Top Up MLBB Now',
    ctaTo: '/games/mobile-legends',
    promoCode: null as string | null,
  },
  {
    id: 'promo',
    badge: locale.value === 'km' ? '🎁 ប្រូម៉ូសិនពិសេស' : '🎁 Welcome Promotion',
    title: locale.value === 'km'
      ? 'បញ្ចុះតម្លៃ 10% សម្រាប់ការបញ្ចូលទឹកប្រាក់ហ្គេម!'
      : 'Get 10% OFF Your Game Top-Up Today!',
    subtitle: locale.value === 'km'
      ? 'ប្រើកូដ KHMER10 នៅពេលទូទាត់ ដើម្បីទទួលបានការបញ្ចុះតម្លៃរហូតដល់ $2.00 លើហ្គេមទាំងអស់។'
      : 'Apply code KHMER10 at checkout to save up to $2.00 on all eligible game packages.',
    image: '/images/games/honor-of-kings.jpg',
    accent: 'from-indigo-950/95 via-[#0E1028]/85 to-blue-900/35',
    ctaLabel: locale.value === 'km' ? 'មើលហ្គេមទាំងអស់' : 'Browse All Games',
    ctaTo: '/games',
    promoCode: 'KHMER10',
  },
  {
    id: 'ff-pubg',
    badge: locale.value === 'km' ? '⚡ បញ្ចូលដោយស្វ័យប្រវត្តិក្នុង ៣ វិនាទី' : '⚡ 3-Second Auto Delivery',
    title: locale.value === 'km'
      ? 'Free Fire & PUBG Mobile — តម្លៃពិសេសទាំង USD & KHR'
      : 'Free Fire & PUBG Mobile — Best Rates in USD & KHR',
    subtitle: locale.value === 'km'
      ? 'ជ្រើសរើសកញ្ចប់ពេជ្រ ឬ UC ស្កេន KHQR តាម ABA Mobile ឬធនាគារបាគងណាមួយ ទទួលបានភ្លាមៗ។'
      : 'Pick your Diamonds or UC package, scan KHQR with ABA Mobile or any Bakong bank, and play instantly.',
    image: '/images/games/free-fire.jpg',
    accent: 'from-slate-950/95 via-[#18100C]/85 to-amber-900/30',
    ctaLabel: locale.value === 'km' ? 'បញ្ចូល Free Fire' : 'Top Up Free Fire',
    ctaTo: '/games/free-fire',
    promoCode: null as string | null,
  },
]);

function nextSlide() {
  activeIndex.value = (activeIndex.value + 1) % slides.value.length;
}

function prevSlide() {
  activeIndex.value = (activeIndex.value - 1 + slides.value.length) % slides.value.length;
}

async function copyCode(code: string) {
  if (!import.meta.client) return;
  await navigator.clipboard.writeText(code);
  copied.value = true;
  toast.success(locale.value === 'km' ? `បានចម្លងកូដ ${code} រួចរាល់!` : `Copied code ${code}!`);
  setTimeout(() => { copied.value = false; }, 2200);
}

let timer: ReturnType<typeof setInterval> | null = null;

onMounted(() => {
  const reducedMotion = window.matchMedia?.('(prefers-reduced-motion: reduce)').matches;
  if (!reducedMotion) {
    timer = setInterval(() => {
      if (!isPaused.value) nextSlide();
    }, 5500);
  }
});

onUnmounted(() => {
  if (timer) clearInterval(timer);
});
</script>

<template>
  <section
    class="relative overflow-hidden rounded-3xl border border-white/15 bg-[#090F1E] shadow-2xl"
    @mouseenter="isPaused = true"
    @mouseleave="isPaused = false"
  >
    <div class="relative min-h-[260px] sm:min-h-[340px] lg:min-h-[380px] flex items-center">
      <!-- Background Image Layer -->
      <img
        :key="slides[activeIndex].id"
        :src="slides[activeIndex].image"
        :alt="slides[activeIndex].title"
        class="absolute inset-0 h-full w-full object-cover object-center animate-ken-burns"
      />
      <div
        class="absolute inset-0 bg-gradient-to-r transition-colors duration-500"
        :class="slides[activeIndex].accent"
      />
      <div class="absolute inset-0 bg-gradient-to-t from-[#070B16]/85 via-transparent to-transparent" />

      <!-- Foreground Slide Content -->
      <Transition name="hero-slide" mode="out-in">
        <div :key="slides[activeIndex].id" class="relative z-10 w-full px-5 sm:px-10 lg:px-14 pt-7 pb-12 sm:py-12 max-w-3xl space-y-3.5 sm:space-y-5">
          <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-400/40 bg-black/45 backdrop-blur-md px-3 py-1 text-[10px] sm:text-xs font-bold text-sky-300">
            <Sparkles class="h-3 w-3 sm:h-3.5 sm:w-3.5 text-amber-400" />
            {{ slides[activeIndex].badge }}
          </span>

          <h1 class="text-xl sm:text-4xl lg:text-[42px] font-extrabold tracking-tight text-white leading-[1.2] drop-shadow">
            {{ slides[activeIndex].title }}
          </h1>

          <p class="text-xs sm:text-sm lg:text-base text-slate-200/90 leading-relaxed max-w-xl">
            {{ slides[activeIndex].subtitle }}
          </p>

          <div class="flex flex-wrap items-center gap-2.5 sm:gap-3 pt-1">
            <NuxtLink
              :to="slides[activeIndex].ctaTo"
              class="btn-primary-glow inline-flex items-center justify-center gap-2 rounded-xl sm:rounded-2xl px-5 sm:px-6 py-2.5 sm:py-3 text-xs sm:text-sm font-extrabold text-white"
            >
              <Zap class="h-3.5 w-3.5 sm:h-4 sm:w-4 fill-white" />
              <span>{{ slides[activeIndex].ctaLabel }}</span>
              <ArrowRight class="h-3.5 w-3.5 sm:h-4 sm:w-4" />
            </NuxtLink>

            <button
              v-if="slides[activeIndex].promoCode"
              type="button"
              class="inline-flex items-center gap-2 rounded-2xl border border-sky-400/50 bg-black/60 backdrop-blur-md hover:bg-black/80 px-4 py-3 font-mono text-xs sm:text-sm font-extrabold text-sky-300 transition-colors"
              @click="copyCode(slides[activeIndex].promoCode!)"
            >
              <span>{{ slides[activeIndex].promoCode }}</span>
              <Check v-if="copied" class="h-4 w-4 text-emerald-400" />
              <Copy v-else class="h-4 w-4 text-slate-300" />
            </button>
          </div>
        </div>
      </Transition>

      <!-- Prev / Next Arrows -->
      <button
        type="button"
        aria-label="Previous slide"
        class="hidden sm:flex absolute left-3 top-1/2 -translate-y-1/2 z-20 h-10 w-10 items-center justify-center rounded-full bg-black/45 hover:bg-black/75 border border-white/15 text-white backdrop-blur-md transition-colors"
        @click="prevSlide"
      >
        <ChevronLeft class="h-5 w-5" />
      </button>
      <button
        type="button"
        aria-label="Next slide"
        class="hidden sm:flex absolute right-3 top-1/2 -translate-y-1/2 z-20 h-10 w-10 items-center justify-center rounded-full bg-black/45 hover:bg-black/75 border border-white/15 text-white backdrop-blur-md transition-colors"
        @click="nextSlide"
      >
        <ChevronRight class="h-5 w-5" />
      </button>

      <!-- Indicator Dots -->
      <div class="absolute bottom-4 left-6 sm:left-10 lg:left-14 z-20 flex items-center gap-2">
        <button
          v-for="(s, i) in slides"
          :key="s.id"
          type="button"
          :aria-label="`Go to slide ${i + 1}`"
          class="h-2 rounded-full transition-all duration-300"
          :class="activeIndex === i ? 'w-8 bg-sky-400' : 'w-2 bg-white/35 hover:bg-white/60'"
          @click="activeIndex = i"
        />
      </div>
    </div>
  </section>
</template>
