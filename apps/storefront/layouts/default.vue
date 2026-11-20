<script setup lang="ts">
import { Gamepad2, Globe, ShieldCheck, User, Menu, X, Zap, Headphones, Lock, Home, Search } from 'lucide-vue-next';
import { usePreferencesStore } from '~/stores/preferences';
import { useAuthStore } from '~/stores/auth';

const { locale, setLocale } = useI18n();
const route = useRoute();
const prefs = usePreferencesStore();
const auth = useAuthStore();
const mobileMenuOpen = ref(false);

const hideMobileBottomNav = computed(() => route.path.startsWith('/games/') && route.path !== '/games');

onMounted(() => {
  auth.hydrate();
});

function toggleLocale() {
  setLocale(locale.value === 'km' ? 'en' : 'km');
}

function toggleCurrency() {
  prefs.setCurrency(prefs.currency === 'USD' ? 'KHR' : 'USD');
}
</script>

<template>
  <div class="min-h-screen flex flex-col text-brand-text">
    <!-- Top Live Status Micro-Bar -->
    <div class="border-b border-white/5 bg-[#050811] text-[11px] text-brand-muted">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-8 flex items-center justify-between">
        <div class="flex items-center gap-2">
          <span class="relative flex h-2 w-2">
            <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-emerald-400 opacity-75" />
            <span class="relative inline-flex rounded-full h-2 w-2 bg-emerald-500" />
          </span>
          <span class="text-slate-300 font-medium">
            {{ locale === 'km' ? 'ប្រព័ន្ធបញ្ចូលស្វ័យប្រវត្តិដំណើរការ ២៤/៧' : 'Automatic Fulfillment Online 24/7' }}
          </span>
        </div>
        <div class="hidden sm:flex items-center gap-4">
          <span class="inline-flex items-center gap-1"><Lock class="h-3 w-3 text-brand-accent" /> KHQR Verified</span>
          <span class="inline-flex items-center gap-1"><Zap class="h-3 w-3 text-amber-400" /> 1 USD = {{ prefs.exchangeRateUsdToKhr.toLocaleString() }} ៛</span>
        </div>
      </div>
    </div>

    <!-- Main Sticky Header -->
    <header class="sticky top-0 z-40 border-b border-white/10 bg-[#070B16]/85 backdrop-blur-xl">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 h-16 flex items-center justify-between gap-4">
        <NuxtLink to="/" class="flex items-center gap-2.5 sm:gap-3 font-bold text-lg tracking-tight text-white group">
          <img
            src="/images/brand/vanntopup-logo.jpg"
            alt="VannTopUp Logo"
            class="h-10 w-10 rounded-xl object-cover border border-sky-400/40 shadow-lg shadow-blue-600/30 transition-transform group-hover:scale-105"
          />
          <div class="leading-tight">
            <span class="block text-base font-bold tracking-tight text-white">Vann<span class="text-sky-400">TopUp</span></span>
            <span class="block text-[10px] font-medium text-slate-400 tracking-wider uppercase">Cambodia Gaming</span>
          </div>
        </NuxtLink>

        <nav class="hidden md:flex items-center gap-1 text-sm font-medium" aria-label="Main navigation">
          <NuxtLink
            v-for="item in [
              { to: '/', label: $t('nav.home') },
              { to: '/games', label: $t('nav.games') },
              { to: '/track', label: $t('nav.track') },
              { to: '/faq', label: $t('nav.faq') },
              { to: '/contact', label: $t('nav.contact') },
            ]"
            :key="item.to"
            :to="item.to"
            class="px-3.5 py-2 rounded-xl transition-colors"
            :class="route.path === item.to ? 'bg-white/10 text-white font-semibold' : 'text-brand-muted hover:text-white hover:bg-white/5'"
          >
            {{ item.label }}
          </NuxtLink>
        </nav>

        <div class="flex items-center gap-2">
          <!-- Segmented Currency Pill -->
          <button
            type="button"
            class="px-3 py-1.5 rounded-xl border border-white/10 bg-brand-surface/90 text-xs font-bold text-brand-accent hover:border-brand-accent/60 active:scale-95 transition-all duration-150"
            aria-label="Switch currency"
            @click="toggleCurrency"
          >
            {{ prefs.currency === 'USD' ? '$ USD' : '៛ KHR' }}
          </button>

          <!-- Language Pill -->
          <button
            type="button"
            class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-xl border border-white/10 bg-brand-surface/90 text-xs font-semibold text-white hover:border-brand-accent/60 active:scale-95 transition-all duration-150"
            aria-label="Switch language"
            @click="toggleLocale"
          >
            <Globe class="h-3.5 w-3.5 text-brand-accent" />
            <span>{{ locale === 'km' ? 'ខ្មែរ' : 'EN' }}</span>
          </button>

          <NuxtLink
            to="/account"
            class="hidden sm:inline-flex items-center gap-1.5 px-4 py-2 rounded-xl btn-primary-glow text-xs font-bold text-white"
          >
            <User class="h-3.5 w-3.5" />
            <span>{{ auth.isAuthenticated ? $t('nav.account') : $t('nav.login') }}</span>
          </NuxtLink>

          <button
            type="button"
            class="md:hidden p-2 rounded-xl border border-white/10 text-brand-muted hover:text-white"
            aria-label="Toggle menu"
            @click="mobileMenuOpen = !mobileMenuOpen"
          >
            <X v-if="mobileMenuOpen" class="h-5 w-5" />
            <Menu v-else class="h-5 w-5" />
          </button>
        </div>
      </div>

      <Transition name="drawer">
        <div v-if="mobileMenuOpen" class="md:hidden border-t border-white/10 bg-[#0B1020] px-4 py-4 space-y-1.5">
          <NuxtLink to="/" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-white hover:bg-white/5" @click="mobileMenuOpen = false">{{ $t('nav.home') }}</NuxtLink>
          <NuxtLink to="/games" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-white hover:bg-white/5" @click="mobileMenuOpen = false">{{ $t('nav.games') }}</NuxtLink>
          <NuxtLink to="/track" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-white hover:bg-white/5" @click="mobileMenuOpen = false">{{ $t('nav.track') }}</NuxtLink>
          <NuxtLink to="/faq" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-white hover:bg-white/5" @click="mobileMenuOpen = false">{{ $t('nav.faq') }}</NuxtLink>
          <NuxtLink to="/contact" class="block px-3 py-2.5 rounded-xl text-sm font-medium text-white hover:bg-white/5" @click="mobileMenuOpen = false">{{ $t('nav.contact') }}</NuxtLink>
          <NuxtLink to="/account" class="block px-3 py-2.5 rounded-xl text-sm font-bold text-brand-accent bg-brand-primary/15" @click="mobileMenuOpen = false">{{ $t('nav.account') }}</NuxtLink>
        </div>
      </Transition>
    </header>

    <main class="flex-1">
      <slot />
    </main>

    <!-- Premium Multi-Column Footer -->
    <footer class="border-t border-white/10 bg-[#050811] mt-20">
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 grid grid-cols-1 md:grid-cols-4 gap-8">
        <div class="md:col-span-2 space-y-3">
          <div class="flex items-center gap-2.5 font-bold text-white text-lg">
            <img src="/images/brand/vanntopup-logo.jpg" alt="VannTopUp Logo" class="h-8 w-8 rounded-lg object-cover border border-sky-400/35" />
            <span>Vann<span class="text-sky-400">TopUp</span></span>
          </div>
          <p class="text-xs text-brand-muted max-w-md leading-relaxed">{{ $t('brand.tagline') }}</p>
          <div class="flex flex-wrap items-center gap-2 pt-2">
            <span class="px-2.5 py-1 rounded-lg bg-red-600/20 border border-red-500/30 text-[11px] font-bold text-red-300">KHQR</span>
            <span class="px-2.5 py-1 rounded-lg bg-blue-600/20 border border-blue-500/30 text-[11px] font-bold text-blue-300">ABA PayWay</span>
            <span class="px-2.5 py-1 rounded-lg bg-sky-500/15 border border-sky-400/30 text-[11px] font-bold text-sky-300">Bakong</span>
            <span class="px-2.5 py-1 rounded-lg bg-emerald-500/15 border border-emerald-400/30 text-[11px] font-bold text-emerald-300">USD / KHR</span>
          </div>
        </div>

        <div class="space-y-2.5">
          <h3 class="text-xs font-bold uppercase tracking-wider text-white">Platform</h3>
          <div class="flex flex-col gap-2 text-xs text-brand-muted">
            <NuxtLink to="/games" class="hover:text-brand-accent">{{ $t('nav.games') }}</NuxtLink>
            <NuxtLink to="/track" class="hover:text-brand-accent">{{ $t('nav.track') }}</NuxtLink>
            <NuxtLink to="/account" class="hover:text-brand-accent">{{ $t('nav.account') }}</NuxtLink>
            <NuxtLink to="/faq" class="hover:text-brand-accent">{{ $t('nav.faq') }}</NuxtLink>
          </div>
        </div>

        <div class="space-y-2.5">
          <h3 class="text-xs font-bold uppercase tracking-wider text-white">Support & Legal</h3>
          <div class="flex flex-col gap-2 text-xs text-brand-muted">
            <NuxtLink to="/contact" class="hover:text-brand-accent inline-flex items-center gap-1.5"><Headphones class="h-3.5 w-3.5" /> {{ $t('nav.contact') }}</NuxtLink>
            <NuxtLink to="/legal/terms" class="hover:text-brand-accent">{{ $t('footer.terms') }}</NuxtLink>
            <NuxtLink to="/legal/privacy" class="hover:text-brand-accent">{{ $t('footer.privacy') }}</NuxtLink>
            <NuxtLink to="/legal/refund" class="hover:text-brand-accent">{{ $t('footer.refund') }}</NuxtLink>
          </div>
        </div>
      </div>
      <div class="border-t border-white/5 py-4 pb-24 md:pb-4 text-center text-[11px] text-brand-muted">
        {{ $t('footer.rights') }}
      </div>
    </footer>

    <!-- Floating Native-Style Mobile Bottom Navigation Dock -->
    <nav
      v-if="!hideMobileBottomNav"
      aria-label="Mobile bottom navigation"
      class="md:hidden fixed bottom-3 inset-x-3 z-30 pb-[env(safe-area-inset-bottom)]"
    >
      <div class="mx-auto max-w-md rounded-2xl border border-white/15 bg-[#0A1124]/90 backdrop-blur-2xl shadow-[0_14px_38px_rgba(0,0,0,0.72)] px-2 py-1.5">
        <div class="grid grid-cols-5 items-center gap-1">
          <!-- Home -->
          <NuxtLink
            to="/"
            class="relative flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[10px] font-bold transition-all duration-200 active:scale-90"
            :class="route.path === '/' ? 'text-sky-300 bg-sky-500/15' : 'text-slate-400 hover:text-white'"
          >
            <span v-if="route.path === '/'" class="absolute top-0.5 w-5 h-0.5 rounded-full bg-sky-400 shadow-[0_0_8px_#38BDF8]" />
            <Home class="h-4 w-4 transition-transform duration-200" :class="route.path === '/' ? 'scale-110 text-sky-400' : ''" />
            <span class="truncate max-w-full px-0.5">{{ $t('nav.home') }}</span>
          </NuxtLink>

          <!-- Games -->
          <NuxtLink
            to="/games"
            class="relative flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[10px] font-bold transition-all duration-200 active:scale-90"
            :class="route.path === '/games' ? 'text-sky-300 bg-sky-500/15' : 'text-slate-400 hover:text-white'"
          >
            <span v-if="route.path === '/games'" class="absolute top-0.5 w-5 h-0.5 rounded-full bg-sky-400 shadow-[0_0_8px_#38BDF8]" />
            <Gamepad2 class="h-4 w-4 transition-transform duration-200" :class="route.path === '/games' ? 'scale-110 text-sky-400' : ''" />
            <span class="truncate max-w-full px-0.5">{{ $t('nav.games') }}</span>
          </NuxtLink>

          <!-- Center Elevated Instant Top-Up Orb -->
          <div class="flex flex-col items-center justify-center -mt-6">
            <NuxtLink
              to="/games/mobile-legends"
              aria-label="Quick Top-Up"
              class="h-12 w-12 rounded-2xl btn-primary-glow border-2 border-[#070B16] ring-2 ring-sky-400/40 flex items-center justify-center text-white shadow-xl shadow-blue-600/45 transition-transform active:scale-90"
            >
              <Zap class="h-5 w-5 fill-white" />
            </NuxtLink>
            <span class="text-[9px] font-extrabold text-sky-300 mt-1 tracking-wide">
              {{ locale === 'km' ? 'បញ្ចូល' : 'Top Up' }}
            </span>
          </div>

          <!-- Track -->
          <NuxtLink
            to="/track"
            class="relative flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[10px] font-bold transition-all duration-200 active:scale-90"
            :class="route.path.startsWith('/track') ? 'text-sky-300 bg-sky-500/15' : 'text-slate-400 hover:text-white'"
          >
            <span v-if="route.path.startsWith('/track')" class="absolute top-0.5 w-5 h-0.5 rounded-full bg-sky-400 shadow-[0_0_8px_#38BDF8]" />
            <Search class="h-4 w-4 transition-transform duration-200" :class="route.path.startsWith('/track') ? 'scale-110 text-sky-400' : ''" />
            <span class="truncate max-w-full px-0.5">{{ $t('nav.track') }}</span>
          </NuxtLink>

          <!-- Account -->
          <NuxtLink
            to="/account"
            class="relative flex flex-col items-center justify-center gap-1 rounded-xl py-2 text-[10px] font-bold transition-all duration-200 active:scale-90"
            :class="route.path.startsWith('/account') ? 'text-sky-300 bg-sky-500/15' : 'text-slate-400 hover:text-white'"
          >
            <span v-if="route.path.startsWith('/account')" class="absolute top-0.5 w-5 h-0.5 rounded-full bg-sky-400 shadow-[0_0_8px_#38BDF8]" />
            <User class="h-4 w-4 transition-transform duration-200" :class="route.path.startsWith('/account') ? 'scale-110 text-sky-400' : ''" />
            <span class="truncate max-w-full px-0.5">{{ $t('nav.account') }}</span>
          </NuxtLink>
        </div>
      </div>
    </nav>

    <FloatingTelegramWidget />
    <ToastContainer />
  </div>
</template>
