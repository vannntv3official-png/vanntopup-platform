<script setup lang="ts">
import { Search, Hash, KeyRound, ShieldCheck, Clock, ArrowRight, ClipboardPaste, Headphones } from 'lucide-vue-next';

const router = useRouter();
const { locale } = useI18n();
const toast = useToast();

const orderNumber = ref('');
const trackingToken = ref('');
const recentOrders = ref<Array<{ orderNumber: string; token: string }>>([]);

onMounted(() => {
  if (!import.meta.client) return;
  const found: Array<{ orderNumber: string; token: string }> = [];
  for (let i = 0; i < sessionStorage.length; i++) {
    const key = sessionStorage.key(i);
    if (key && key.startsWith('ktu_track_')) {
      const num = key.replace('ktu_track_', '');
      const tok = sessionStorage.getItem(key) || '';
      if (num && tok) found.push({ orderNumber: num, token: tok });
    }
  }
  recentOrders.value = found.reverse().slice(0, 5);
});

async function pasteToken() {
  if (!import.meta.client || !navigator.clipboard) return;
  try {
    const text = await navigator.clipboard.readText();
    if (text.trim()) {
      trackingToken.value = text.trim();
      toast.info(locale.value === 'km' ? 'បានបិទភ្ជាប់កូដតាមដាន!' : 'Tracking token pasted!');
    }
  } catch {
    toast.error(locale.value === 'km' ? 'សូមចុច Ctrl+V ដើម្បីបិទភ្ជាប់' : 'Press Ctrl+V to paste your token');
  }
}

function handleTrack() {
  if (!orderNumber.value.trim() || !trackingToken.value.trim()) return;
  router.push({
    path: `/track/${orderNumber.value.trim()}`,
    query: { token: trackingToken.value.trim() },
  });
}
</script>

<template>
  <div class="mx-auto max-w-5xl px-4 sm:px-6 py-10 sm:py-14 space-y-8">
    <!-- Header -->
    <div class="animate-fade-up max-w-2xl space-y-2">
      <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-500/15 border border-sky-400/30 px-3 py-1 text-[11px] font-bold text-sky-300">
        <Search class="h-3.5 w-3.5" />
        {{ locale === 'km' ? 'ពិនិត្យស្ថានភាពផ្ទាល់ ២៤/៧' : 'REAL-TIME ORDER TRACKING' }}
      </span>
      <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">{{ $t('track.title') }}</h1>
      <p class="text-sm text-slate-400 leading-relaxed">
        {{ locale === 'km'
          ? 'បញ្ចូលលេខការបញ្ជាទិញ និងកូដតាមដានសម្ងាត់ (64 តួអក្សរ) ដែលអ្នកបានទទួលពេលទូទាត់ ដើម្បីពិនិត្យស្ថានភាពបញ្ចូលទឹកប្រាក់ហ្គេម។'
          : 'Enter your Order Number and 64-character secret Tracking Token from checkout to view live payment & delivery progress.' }}
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Left: Tracking Form -->
      <div class="lg:col-span-7 glass-card rounded-3xl p-6 sm:p-8 animate-fade-up" style="animation-delay: 60ms">
        <form class="space-y-5" @submit.prevent="handleTrack">
          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-300">{{ $t('track.orderNumberLabel') }}</label>
            <div class="relative">
              <Hash class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model="orderNumber"
                type="text"
                required
                placeholder="KTU-20261120-XXXXXX"
                class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-10 pr-4 py-3.5 text-sm text-white font-mono placeholder-slate-500 focus:border-sky-400 focus:outline-none"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <div class="flex items-center justify-between">
              <label class="block text-xs font-semibold text-slate-300">{{ $t('track.tokenLabel') }}</label>
              <button
                type="button"
                class="inline-flex items-center gap-1 text-[11px] font-bold text-sky-400 hover:text-sky-300"
                @click="pasteToken"
              >
                <ClipboardPaste class="h-3.5 w-3.5" />
                <span>{{ locale === 'km' ? 'បិទភ្ជាប់ (Paste)' : 'Paste' }}</span>
              </button>
            </div>
            <div class="relative">
              <KeyRound class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model="trackingToken"
                type="text"
                required
                :placeholder="locale === 'km' ? 'កូដតាមដានសម្ងាត់ 64 តួអក្សរ' : '64-character secret tracking token'"
                class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-10 pr-4 py-3.5 text-sm text-white font-mono placeholder-slate-500 focus:border-sky-400 focus:outline-none"
              />
            </div>
          </div>

          <button
            type="submit"
            class="w-full btn-primary-glow rounded-2xl py-3.5 text-sm font-extrabold text-white inline-flex items-center justify-center gap-2"
          >
            <Search class="h-4 w-4" />
            <span>{{ $t('track.submit') }}</span>
            <ArrowRight class="h-4 w-4" />
          </button>
        </form>
      </div>

      <!-- Right: Recent Browser Orders & Security Info -->
      <div class="lg:col-span-5 space-y-4 animate-fade-up" style="animation-delay: 120ms">
        <!-- Quick Access to Recent Orders in This Session -->
        <div v-if="recentOrders.length > 0" class="glass-card rounded-3xl p-5 space-y-3 border-sky-400/30">
          <h2 class="text-sm font-extrabold text-white flex items-center gap-2">
            <Clock class="h-4 w-4 text-sky-400" />
            <span>{{ locale === 'km' ? 'ការបញ្ជាទិញថ្មីៗលើឧបករណ៍នេះ' : 'Recent Orders on This Device' }}</span>
          </h2>
          <div class="space-y-2">
            <NuxtLink
              v-for="item in recentOrders"
              :key="item.orderNumber"
              :to="{ path: `/track/${item.orderNumber}`, query: { token: item.token } }"
              class="flex items-center justify-between rounded-xl border border-white/10 bg-[#070B16] hover:border-sky-400/50 px-3.5 py-2.5 text-xs transition-colors"
            >
              <span class="font-mono font-bold text-white">{{ item.orderNumber }}</span>
              <span class="text-sky-400 font-bold inline-flex items-center gap-1">
                {{ locale === 'km' ? 'មើល' : 'View' }} <ArrowRight class="h-3 w-3" />
              </span>
            </NuxtLink>
          </div>
        </div>

        <!-- Privacy & Security Explainer -->
        <div class="glass-card rounded-3xl p-6 space-y-3">
          <div class="flex items-center gap-2.5">
            <span class="h-9 w-9 rounded-xl bg-emerald-500/15 border border-emerald-400/30 flex items-center justify-center shrink-0">
              <ShieldCheck class="h-5 w-5 text-emerald-400" />
            </span>
            <h2 class="text-sm font-extrabold text-white">
              {{ locale === 'km' ? 'ហេតុអ្វីត្រូវការកូដតាមដានសម្ងាត់?' : 'Why a Secret Tracking Token?' }}
            </h2>
          </div>
          <p class="text-xs text-slate-400 leading-relaxed">
            {{ locale === 'km'
              ? 'ដើម្បីការពារឯកជនភាព Player ID និងវិក្កយបត្ររបស់អ្នក មិនឱ្យអ្នកដទៃអាចទាយលេខការបញ្ជាទិញចូលមើលបាន។ បើអ្នកមានគណនី VannTopUp អ្នកអាចចូលគណនីដើម្បីមើលការបញ្ជាទិញទាំងអស់ដោយមិនចាំបាច់ប្រើកូដទេ។'
              : 'To protect your Player ID and receipt from unauthorized lookup, guest orders require a cryptographic tracking token. Registered members can view all orders directly in Account History.' }}
          </p>
          <div class="pt-2 flex flex-wrap gap-2">
            <NuxtLink to="/account" class="rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 px-3.5 py-2 text-xs font-bold text-sky-300">
              {{ locale === 'km' ? 'ចូលគណនី' : 'Sign In' }}
            </NuxtLink>
            <NuxtLink to="/contact" class="inline-flex items-center gap-1.5 rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 px-3.5 py-2 text-xs font-bold text-slate-200">
              <Headphones class="h-3.5 w-3.5 text-amber-400" />
              <span>{{ locale === 'km' ? 'ភ្លេចកូដតាមដាន?' : 'Lost Token? Contact Us' }}</span>
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
