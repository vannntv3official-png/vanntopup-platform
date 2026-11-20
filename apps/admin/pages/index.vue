<script setup lang="ts">
import { formatMoney } from '@topup/shared-types';
import {
  PauseCircle, PlayCircle, RefreshCw, DollarSign, TrendingUp,
  CheckCircle2, AlertTriangle, ShoppingBag, ShieldAlert, ArrowRight, Server
} from 'lucide-vue-next';

const { request } = useAdminApi();
const toast = useAdminToast();
const overview = ref<any>(null);
const loading = ref(true);

const fulfillmentRate = computed(() => {
  if (!overview.value) return 100;
  const paid = overview.value.metrics.paid_orders || 0;
  if (paid === 0) return 100;
  return Math.round((overview.value.metrics.successful_fulfillments / paid) * 100);
});

async function loadOverview() {
  loading.value = true;
  try {
    const res = await request<{ data: any }>('/admin/overview');
    overview.value = res.data;
  } finally {
    loading.value = false;
  }
}

async function toggleGlobalPause() {
  if (!overview.value) return;
  const next = !overview.value.controls.fulfillment_global_pause;
  await request('/admin/settings', {
    method: 'PATCH',
    body: { fulfillment_global_pause: next },
  });
  toast.info(next ? 'Global fulfillment PAUSED — paid orders will hold for review.' : 'Global fulfillment RESUMED.');
  await loadOverview();
}

onMounted(loadOverview);
</script>

<template>
  <div class="space-y-6">
    <!-- Top Header & Controls -->
    <div class="flex flex-wrap items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl sm:text-3xl font-bold text-white tracking-tight">Operations Overview</h1>
        <p class="text-xs text-slate-400 mt-0.5">Live revenue, fulfillment reliability, reconciliation & emergency controls</p>
      </div>
      <div class="flex items-center gap-2.5">
        <button
          v-if="overview"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold transition-all active:scale-95"
          :class="overview.controls.fulfillment_global_pause
            ? 'bg-amber-400 text-slate-950 shadow-lg shadow-amber-500/25'
            : 'bg-red-600/85 hover:bg-red-600 text-white'"
          @click="toggleGlobalPause"
        >
          <PlayCircle v-if="overview.controls.fulfillment_global_pause" class="h-4 w-4" />
          <PauseCircle v-else class="h-4 w-4" />
          <span>{{ overview.controls.fulfillment_global_pause ? 'Resume Fulfillment' : 'Emergency Pause' }}</span>
        </button>
        <button type="button" class="rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 p-2.5 text-sky-400 transition-colors" @click="loadOverview">
          <RefreshCw class="h-4 w-4" :class="{ 'animate-spin': loading }" />
        </button>
      </div>
    </div>

    <!-- Active Emergency Pause Warning -->
    <div v-if="overview?.controls?.fulfillment_global_pause" class="rounded-2xl border border-amber-400/50 bg-amber-500/15 p-4 flex items-center gap-3 text-xs font-semibold text-amber-200">
      <ShieldAlert class="h-5 w-5 text-amber-400 shrink-0" />
      <span>GLOBAL FULFILLMENT PAUSE IS ACTIVE: Newly paid orders are safely placed into Needs Review instead of submitting to suppliers.</span>
    </div>

    <!-- Primary Financial KPI Row -->
    <div v-if="overview" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="admin-card rounded-2xl p-5 space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-400">
          <span>Revenue (USD)</span>
          <DollarSign class="h-4 w-4 text-sky-400" />
        </div>
        <p class="text-2xl font-bold text-white tabular-nums">{{ formatMoney(overview.metrics.revenue_usd_minor, 'USD') }}</p>
        <p class="text-[11px] text-slate-400">FX Rate: 1 USD = {{ overview.controls.exchange_rate_usd_to_khr }} ៛</p>
      </div>

      <div class="admin-card rounded-2xl p-5 space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-400">
          <span>Revenue (KHR)</span>
          <DollarSign class="h-4 w-4 text-sky-400" />
        </div>
        <p class="text-2xl font-bold text-sky-300 tabular-nums">{{ formatMoney(overview.metrics.revenue_khr_minor, 'KHR') }}</p>
        <p class="text-[11px] text-slate-400">Local Bakong KHQR sales</p>
      </div>

      <div class="admin-card rounded-2xl p-5 space-y-2">
        <div class="flex items-center justify-between text-xs text-slate-400">
          <span>Gross Profit (USD)</span>
          <TrendingUp class="h-4 w-4 text-emerald-400" />
        </div>
        <p class="text-2xl font-bold text-emerald-400 tabular-nums">{{ formatMoney(overview.metrics.gross_profit_usd_minor, 'USD') }}</p>
        <p class="text-[11px] text-emerald-300/80">Fulfillment success: {{ fulfillmentRate }}%</p>
      </div>

      <NuxtLink to="/reconciliation" class="admin-card rounded-2xl p-5 space-y-2 hover:border-amber-400/50 transition-colors">
        <div class="flex items-center justify-between text-xs text-slate-400">
          <span>Open Discrepancies</span>
          <AlertTriangle class="h-4 w-4" :class="overview.metrics.open_discrepancies > 0 ? 'text-amber-400' : 'text-slate-500'" />
        </div>
        <p class="text-2xl font-bold tabular-nums" :class="overview.metrics.open_discrepancies > 0 ? 'text-amber-400' : 'text-white'">
          {{ overview.metrics.open_discrepancies }}
        </p>
        <p class="text-[11px] text-sky-400 inline-flex items-center gap-1">Review ledger <ArrowRight class="h-3 w-3" /></p>
      </NuxtLink>
    </div>

    <!-- Secondary Operational Counters -->
    <div v-if="overview" class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="admin-card rounded-2xl p-4 flex items-center justify-between">
        <div>
          <p class="text-xs text-slate-400">Paid Orders</p>
          <p class="text-xl font-bold text-white tabular-nums mt-0.5">{{ overview.metrics.paid_orders }}</p>
        </div>
        <ShoppingBag class="h-5 w-5 text-sky-400" />
      </div>
      <div class="admin-card rounded-2xl p-4 flex items-center justify-between">
        <div>
          <p class="text-xs text-slate-400">Delivered</p>
          <p class="text-xl font-bold text-emerald-400 tabular-nums mt-0.5">{{ overview.metrics.successful_fulfillments }}</p>
        </div>
        <CheckCircle2 class="h-5 w-5 text-emerald-400" />
      </div>
      <NuxtLink to="/orders" class="admin-card rounded-2xl p-4 flex items-center justify-between hover:border-amber-400/40 transition-colors">
        <div>
          <p class="text-xs text-slate-400">Needs Review</p>
          <p class="text-xl font-bold text-amber-400 tabular-nums mt-0.5">{{ overview.metrics.needs_review_orders }}</p>
        </div>
        <AlertTriangle class="h-5 w-5 text-amber-400" />
      </NuxtLink>
      <div class="admin-card rounded-2xl p-4 flex items-center justify-between">
        <div>
          <p class="text-xs text-slate-400">Failed Orders</p>
          <p class="text-xl font-bold text-red-400 tabular-nums mt-0.5">{{ overview.metrics.failed_orders }}</p>
        </div>
        <ShieldAlert class="h-5 w-5 text-red-400" />
      </div>
    </div>

    <!-- Supplier Reliability Grid -->
    <div v-if="overview" class="admin-card rounded-3xl p-6 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-white flex items-center gap-2">
          <Server class="h-4 w-4 text-sky-400" /> Supplier Health & Routing
        </h2>
        <NuxtLink to="/suppliers" class="text-xs font-semibold text-sky-400 hover:underline">Manage Suppliers →</NuxtLink>
      </div>
      <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
        <div v-for="s in overview.suppliers" :key="s.id" class="rounded-2xl border border-white/10 bg-[#070B16]/85 p-4 text-xs space-y-2">
          <div class="flex items-center justify-between gap-2">
            <p class="font-bold text-white truncate">{{ s.name }}</p>
            <span
              class="rounded-full px-2.5 py-0.5 text-[10px] font-bold uppercase shrink-0"
              :class="s.health_status === 'healthy' ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'"
            >
              {{ s.health_status }}
            </span>
          </div>
          <p class="text-slate-400">Mode: <span class="text-slate-200 font-semibold uppercase">{{ s.environment }}</span> • Reliability: <span class="text-sky-300 font-bold">{{ s.reliability_score }}%</span></p>
          <p class="text-slate-400">Active: {{ s.is_active ? 'Yes' : 'No' }} • Paused: {{ s.is_paused ? 'Yes' : 'No' }}</p>
        </div>
      </div>
    </div>
  </div>
</template>
