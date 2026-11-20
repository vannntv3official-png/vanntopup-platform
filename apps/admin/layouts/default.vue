<script setup lang="ts">
import {
  LayoutDashboard, ShoppingBag, Gamepad2, Server, Scale, Settings,
  LogOut, Menu, X, ExternalLink
} from 'lucide-vue-next';
import { useAdminAuthStore } from '~/stores/adminAuth';

const route = useRoute();
const router = useRouter();
const auth = useAdminAuthStore();
const { request } = useAdminApi();
const mobileNavOpen = ref(false);

onMounted(() => {
  auth.hydrate();
  if (!auth.token && route.path !== '/login') {
    router.replace('/login');
  }
});

// Close mobile drawer on route change
watch(() => route.path, () => {
  mobileNavOpen.value = false;
});

async function handleLogout() {
  try {
    await request('/auth/logout', { method: 'POST' });
  } finally {
    auth.clearSession();
    router.push('/login');
  }
}

const navItems = [
  { to: '/', label: 'Overview', shortLabel: 'Home', icon: LayoutDashboard },
  { to: '/orders', label: 'Orders', shortLabel: 'Orders', icon: ShoppingBag },
  { to: '/catalog', label: 'Games & Packages', shortLabel: 'Catalog', icon: Gamepad2 },
  { to: '/suppliers', label: 'Suppliers', shortLabel: 'Suppliers', icon: Server },
  { to: '/reconciliation', label: 'Reconciliation & Payments', shortLabel: 'Recon', icon: Scale },
  { to: '/operations', label: 'Promos, Customers & Audit', shortLabel: 'Ops', icon: Settings },
];

const mobileBottomItems = [
  navItems[0], // Overview
  navItems[1], // Orders
  navItems[2], // Catalog
  navItems[4], // Recon
  navItems[5], // Ops
];

const currentPageTitle = computed(() => {
  return navItems.find((i) => i.to === route.path)?.shortLabel || 'Admin';
});
</script>

<template>
  <div v-if="route.path === '/login'" class="min-h-screen text-brand-text">
    <slot />
    <AdminToastContainer />
  </div>

  <div v-else class="min-h-screen flex flex-col lg:flex-row text-brand-text">
    <!-- Sticky Mobile Top Header -->
    <header class="lg:hidden sticky top-0 z-30 flex items-center justify-between border-b border-white/10 bg-[#070B16]/90 backdrop-blur-xl px-4 h-14">
      <div class="flex items-center gap-2.5 min-w-0">
        <img src="/images/brand/vanntopup-logo.jpg" alt="VannTopUp" class="h-8 w-8 rounded-lg object-cover border border-sky-400/40 shrink-0" />
        <div class="flex items-center gap-2 min-w-0">
          <span class="text-sm font-bold text-white truncate">Vann<span class="text-sky-400">TopUp</span></span>
          <span class="rounded-md bg-sky-500/15 border border-sky-400/25 px-2 py-0.5 text-[10px] font-bold uppercase tracking-wider text-sky-300">
            {{ currentPageTitle }}
          </span>
        </div>
      </div>
      <button
        type="button"
        aria-label="Toggle navigation menu"
        class="h-10 w-10 flex items-center justify-center rounded-xl border border-white/15 bg-white/5 text-slate-200 hover:bg-white/10 active:scale-95 transition"
        @click="mobileNavOpen = true"
      >
        <Menu class="h-5 w-5" />
      </button>
    </header>

    <!-- Mobile Fixed Slide-Over Overlay Drawer (Never Pushes Content Down!) -->
    <Transition name="fade">
      <div
        v-if="mobileNavOpen"
        class="lg:hidden fixed inset-0 z-50 bg-black/65 backdrop-blur-sm"
        @click="mobileNavOpen = false"
      />
    </Transition>

    <Transition name="slide-drawer">
      <aside
        v-if="mobileNavOpen"
        class="lg:hidden fixed inset-y-0 left-0 z-50 w-[280px] max-w-[85vw] border-r border-white/10 bg-[#0A1020] shadow-2xl flex flex-col justify-between p-4 overflow-y-auto"
      >
        <div class="space-y-5">
          <div class="flex items-center justify-between gap-2 pb-3 border-b border-white/10">
            <div class="flex items-center gap-2.5">
              <img src="/images/brand/vanntopup-logo.jpg" alt="VannTopUp Logo" class="h-9 w-9 rounded-xl object-cover border border-sky-400/40" />
              <div>
                <p class="text-sm leading-tight font-bold text-white">Vann<span class="text-sky-400">TopUp</span></p>
                <p class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold">Admin Console</p>
              </div>
            </div>
            <button
              type="button"
              aria-label="Close navigation menu"
              class="h-9 w-9 flex items-center justify-center rounded-xl border border-white/15 bg-white/5 text-slate-300 hover:text-white"
              @click="mobileNavOpen = false"
            >
              <X class="h-4 w-4" />
            </button>
          </div>

          <nav class="space-y-1.5">
            <NuxtLink
              v-for="item in navItems"
              :key="item.to"
              :to="item.to"
              class="flex items-center gap-3 rounded-xl px-3.5 py-3 text-xs font-semibold transition-all"
              :class="route.path === item.to
                ? 'btn-admin-primary text-white'
                : 'text-slate-300 hover:bg-white/5 hover:text-white'"
              @click="mobileNavOpen = false"
            >
              <component :is="item.icon" class="h-4 w-4 shrink-0" />
              <span>{{ item.label }}</span>
            </NuxtLink>
          </nav>
        </div>

        <div class="space-y-2.5 pt-5 border-t border-white/10 mt-6">
          <a
            href="http://127.0.0.1:3000"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 px-3.5 py-3 text-xs font-semibold text-sky-300 transition-colors"
          >
            <span>Open Storefront</span>
            <ExternalLink class="h-3.5 w-3.5" />
          </a>

          <button
            type="button"
            class="w-full flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 hover:bg-red-500/20 px-3.5 py-3 text-xs font-bold text-red-300 transition-colors"
            @click="handleLogout"
          >
            <LogOut class="h-4 w-4" />
            <span>Sign Out</span>
          </button>
        </div>
      </aside>
    </Transition>

    <!-- Desktop Sticky Sidebar -->
    <aside class="hidden lg:flex lg:sticky lg:top-0 lg:h-screen lg:w-64 lg:shrink-0 border-r border-white/10 bg-[#0A1020]/95 backdrop-blur-xl flex-col justify-between p-4 overflow-y-auto">
      <div class="space-y-6">
        <div class="flex items-center gap-3 px-2 py-1 font-bold text-white">
          <img src="/images/brand/vanntopup-logo.jpg" alt="VannTopUp Logo" class="h-10 w-10 rounded-xl object-cover border border-sky-400/40 shadow-lg shadow-blue-600/25" />
          <div>
            <p class="text-base leading-tight font-bold">Vann<span class="text-sky-400">TopUp</span></p>
            <p class="text-[10px] uppercase tracking-widest text-slate-400 font-semibold">Operations Console</p>
          </div>
        </div>

        <nav class="space-y-1">
          <NuxtLink
            v-for="item in navItems"
            :key="item.to"
            :to="item.to"
            class="flex items-center gap-2.5 rounded-xl px-3.5 py-2.5 text-xs font-semibold transition-all"
            :class="route.path === item.to
              ? 'btn-admin-primary text-white'
              : 'text-slate-400 hover:bg-white/5 hover:text-white'"
          >
            <component :is="item.icon" class="h-4 w-4 shrink-0" />
            <span>{{ item.label }}</span>
          </NuxtLink>
        </nav>
      </div>

      <div class="space-y-2.5 pt-6 border-t border-white/10 mt-6">
        <a
          href="http://127.0.0.1:3000"
          target="_blank"
          rel="noopener noreferrer"
          class="flex items-center justify-between rounded-xl border border-white/10 bg-white/5 hover:bg-white/10 px-3.5 py-2.5 text-xs font-semibold text-sky-300 transition-colors"
        >
          <span>Open Storefront</span>
          <ExternalLink class="h-3.5 w-3.5" />
        </a>

        <button
          type="button"
          class="w-full flex items-center gap-2 rounded-xl border border-red-500/30 bg-red-500/10 hover:bg-red-500/20 px-3.5 py-2.5 text-xs font-bold text-red-300 transition-colors"
          @click="handleLogout"
        >
          <LogOut class="h-4 w-4" />
          <span>Sign Out</span>
        </button>
      </div>
    </aside>

    <!-- Main Workspace (Extra bottom padding on mobile for Bottom Quick-Nav) -->
    <main class="flex-1 min-w-0 px-3.5 pt-4 pb-24 sm:p-6 lg:p-8 overflow-x-clip">
      <div class="mx-auto max-w-7xl">
        <slot />
      </div>
    </main>

    <!-- Floating Mobile Bottom Quick-Nav Bar -->
    <nav class="lg:hidden fixed bottom-0 inset-x-0 z-40 border-t border-white/10 bg-[#070B16]/95 backdrop-blur-xl px-2 py-1.5">
      <div class="grid grid-cols-5 gap-1 max-w-md mx-auto">
        <NuxtLink
          v-for="item in mobileBottomItems"
          :key="item.to"
          :to="item.to"
          class="flex flex-col items-center justify-center gap-1 rounded-xl py-1.5 text-[10px] font-semibold transition-all"
          :class="route.path === item.to
            ? 'text-sky-400 bg-sky-500/15'
            : 'text-slate-400 hover:text-white'"
        >
          <component :is="item.icon" class="h-4 w-4" />
          <span class="truncate">{{ item.shortLabel }}</span>
        </NuxtLink>
      </div>
    </nav>

    <AdminToastContainer />
  </div>
</template>
