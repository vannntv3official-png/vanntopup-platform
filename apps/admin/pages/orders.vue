<script setup lang="ts">
import { formatMoney } from '@topup/shared-types';
import { Download, Search, RefreshCw, X } from 'lucide-vue-next';

const { request } = useAdminApi();
const toast = useAdminToast();

const orders = ref<any[]>([]);
const statusFilter = ref('');
const search = ref('');
const selectedOrder = ref<any | null>(null);
const operatorReason = ref('');
const loading = ref(false);

async function loadOrders() {
  loading.value = true;
  try {
    const params = new URLSearchParams();
    if (statusFilter.value) params.set('status', statusFilter.value);
    if (search.value.trim()) params.set('q', search.value.trim());
    const res = await request<{ data: any[] }>(`/admin/orders?${params.toString()}`);
    orders.value = res.data;
  } finally {
    loading.value = false;
  }
}

async function inspectOrder(id: string) {
  const res = await request<{ data: any }>(`/admin/orders/${id}`);
  selectedOrder.value = res.data;
  operatorReason.value = '';
}

async function retryOrder(id: string) {
  try {
    await request(`/admin/orders/${id}/retry-fulfillment`, { method: 'POST' });
    toast.success('Safe fulfillment retry dispatched.');
    await loadOrders();
    await inspectOrder(id);
  } catch (err: any) {
    toast.error(err?.data?.message || 'Retry failed.');
  }
}

async function resolveReview(id: string, decision: 'mark_completed' | 'mark_failed') {
  const reason = operatorReason.value.trim() || `Operator resolved review as ${decision}.`;
  try {
    await request(`/admin/orders/${id}/resolve-review`, {
      method: 'POST',
      body: { decision, reason },
    });
    toast.success(`Order review resolved (${decision}).`);
    await loadOrders();
    await inspectOrder(id);
  } catch (err: any) {
    toast.error(err?.data?.message || 'Could not resolve review.');
  }
}

async function refundOrder(id: string) {
  const reason = operatorReason.value.trim() || 'Operator approved customer refund.';
  try {
    await request(`/admin/orders/${id}/refund`, {
      method: 'POST',
      body: { reason },
    });
    toast.success('Refund processed & ledger posted.');
    await loadOrders();
    await inspectOrder(id);
  } catch (err: any) {
    toast.error(err?.data?.message || 'Refund failed.');
  }
}

function exportCsv() {
  if (!orders.value.length) {
    toast.info('No orders to export.');
    return;
  }
  const headers = ['OrderNumber', 'Game', 'Package', 'PlayerMasked', 'Currency', 'TotalMinor', 'Status', 'PaymentStatus', 'FulfillmentStatus', 'CreatedAt'];
  const rows = orders.value.map((o) => [
    o.order_number,
    o.game?.name || '',
    o.game_package?.name_en || '',
    o.masked_player_identifier,
    o.currency,
    o.total_minor,
    o.status,
    o.payment_status,
    o.fulfillment_status,
    o.created_at,
  ]);
  const csv = [headers, ...rows].map((r) => r.map((v) => `"${String(v ?? '').replace(/"/g, '""')}"`).join(',')).join('\n');
  const blob = new Blob([csv], { type: 'text/csv;charset=utf-8;' });
  const url = URL.createObjectURL(blob);
  const a = document.createElement('a');
  a.href = url;
  a.download = `vanntopup-orders-${new Date().toISOString().slice(0, 10)}.csv`;
  a.click();
  URL.revokeObjectURL(url);
  toast.success('Orders CSV exported.');
}

function badgeClass(status: string): string {
  if (['completed', 'succeeded', 'paid'].includes(status)) return 'bg-emerald-500/20 text-emerald-300 border-emerald-500/30';
  if (['needs_review', 'uncertain', 'awaiting_payment', 'pending'].includes(status)) return 'bg-amber-500/20 text-amber-300 border-amber-500/30';
  if (['failed', 'expired', 'cancelled'].includes(status)) return 'bg-red-500/20 text-red-300 border-red-500/30';
  return 'bg-sky-500/20 text-sky-300 border-sky-500/30';
}

onMounted(loadOrders);
</script>

<template>
  <div class="space-y-6">
    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white">Order Management</h1>
        <p class="text-xs text-slate-400">Inspect transitions, supplier attempts, safe retries, refunds & CSV export</p>
      </div>
      <div class="grid grid-cols-2 sm:flex sm:flex-wrap items-center gap-2 w-full lg:w-auto">
        <div class="relative col-span-2 sm:flex-1 lg:flex-none">
          <Search class="h-3.5 w-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
          <input v-model="search" type="search" placeholder="Search order # or email..." class="w-full lg:w-52 rounded-xl border border-white/15 bg-[#0A1124] pl-8 pr-3 py-2.5 text-xs text-white focus:border-sky-400 focus:outline-none" @keyup.enter="loadOrders" />
        </div>
        <select v-model="statusFilter" class="col-span-2 sm:col-span-1 rounded-xl border border-white/15 bg-[#0A1124] px-3 py-2.5 text-xs text-white focus:border-sky-400 focus:outline-none" @change="loadOrders">
          <option value="">All Statuses</option>
          <option value="awaiting_payment">Awaiting Payment</option>
          <option value="paid">Paid</option>
          <option value="fulfilling">Fulfilling</option>
          <option value="completed">Completed</option>
          <option value="needs_review">Needs Review</option>
          <option value="failed">Failed</option>
          <option value="refunded">Refunded</option>
        </select>
        <button type="button" class="rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 px-3 py-2.5 text-xs font-semibold text-sky-300 inline-flex items-center justify-center gap-1.5" @click="exportCsv">
          <Download class="h-3.5 w-3.5" /> Export CSV
        </button>
        <button type="button" class="rounded-xl btn-admin-primary px-3.5 py-2.5 text-xs font-bold text-white inline-flex items-center justify-center gap-1.5" @click="loadOrders">
          <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" /> Refresh
        </button>
      </div>
    </div>

    <!-- Mobile Order Cards -->
    <div class="md:hidden space-y-3">
      <div v-if="orders.length === 0" class="admin-card rounded-2xl p-6 text-center text-xs text-slate-400">
        No orders match the current filter.
      </div>
      <div v-for="o in orders" :key="o.id" class="admin-card rounded-2xl p-4 space-y-3 text-xs">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="font-mono font-bold text-white truncate">{{ o.order_number }}</p>
            <p class="text-slate-300 truncate mt-0.5">{{ o.game?.name }} — <span class="text-slate-400">{{ o.game_package?.name_en }}</span></p>
          </div>
          <p class="font-bold text-sky-300 tabular-nums shrink-0">{{ formatMoney(o.total_minor, o.currency) }}</p>
        </div>
        <div class="flex items-center justify-between gap-2 flex-wrap">
          <span class="font-mono text-[11px] text-slate-400">{{ o.masked_player_identifier }}</span>
          <div class="flex items-center gap-1.5">
            <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase" :class="badgeClass(o.status)">{{ o.status }}</span>
            <span class="rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase" :class="badgeClass(o.fulfillment_status)">{{ o.fulfillment_status }}</span>
          </div>
        </div>
        <button type="button" class="w-full rounded-xl border border-sky-400/40 bg-sky-500/10 hover:bg-sky-500/20 py-2 text-xs font-bold text-sky-300" @click="inspectOrder(o.id)">
          Inspect Order
        </button>
      </div>
    </div>

    <!-- Desktop Orders Table -->
    <div class="hidden md:block admin-card rounded-3xl overflow-x-auto">
      <table class="w-full text-left text-xs">
        <thead class="border-b border-white/10 text-slate-400 uppercase text-[11px]">
          <tr>
            <th class="p-4">Order #</th>
            <th class="p-4">Game / Package</th>
            <th class="p-4">Player</th>
            <th class="p-4">Amount</th>
            <th class="p-4">Status</th>
            <th class="p-4">Fulfillment</th>
            <th class="p-4 text-right">Action</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-white/5">
          <tr v-if="orders.length === 0">
            <td colspan="7" class="p-8 text-center text-slate-400">No orders match the current filter.</td>
          </tr>
          <tr v-for="o in orders" :key="o.id" class="hover:bg-white/[0.03] transition-colors">
            <td class="p-4 font-mono font-bold text-white">{{ o.order_number }}</td>
            <td class="p-4 text-slate-200">{{ o.game?.name }} — <span class="text-slate-400">{{ o.game_package?.name_en }}</span></td>
            <td class="p-4 font-mono text-slate-400">{{ o.masked_player_identifier }}</td>
            <td class="p-4 font-bold text-sky-300 tabular-nums">{{ formatMoney(o.total_minor, o.currency) }}</td>
            <td class="p-4">
              <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase" :class="badgeClass(o.status)">{{ o.status }}</span>
            </td>
            <td class="p-4">
              <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase" :class="badgeClass(o.fulfillment_status)">{{ o.fulfillment_status }}</span>
            </td>
            <td class="p-4 text-right">
              <button type="button" class="rounded-lg border border-sky-400/40 bg-sky-500/10 hover:bg-sky-500/20 px-3 py-1 text-xs font-bold text-sky-300" @click="inspectOrder(o.id)">Inspect</button>
            </td>
          </tr>
        </tbody>
      </table>
    </div>

    <!-- Rich Order Inspector Panel -->
    <div v-if="selectedOrder" class="admin-card rounded-3xl border-sky-400/40 p-6 space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
        <div>
          <p class="text-[11px] uppercase tracking-wider text-sky-400 font-bold">Order Inspector</p>
          <h2 class="text-lg font-bold text-white font-mono">{{ selectedOrder.order_number }}</h2>
        </div>
        <button type="button" class="rounded-xl border border-white/10 p-2 text-slate-400 hover:text-white" @click="selectedOrder = null">
          <X class="h-4 w-4" />
        </button>
      </div>

      <!-- Operator Action Bar with Reason Input -->
      <div class="flex flex-col sm:flex-row gap-2.5">
        <input
          v-model="operatorReason"
          type="text"
          placeholder="Operator reason / audit note (required for review or refund)..."
          class="flex-1 rounded-xl border border-white/15 bg-[#060A15] px-3.5 py-2.5 text-xs text-white focus:border-sky-400 focus:outline-none"
        />
        <div class="flex flex-wrap gap-2">
          <button v-if="selectedOrder.fulfillment_status === 'failed'" type="button" class="rounded-xl btn-admin-primary px-3.5 py-2 text-xs font-bold text-white" @click="retryOrder(selectedOrder.id)">Safe Retry</button>
          <button v-if="selectedOrder.status === 'needs_review'" type="button" class="rounded-xl bg-emerald-600 hover:bg-emerald-500 px-3.5 py-2 text-xs font-bold text-white" @click="resolveReview(selectedOrder.id, 'mark_completed')">Resolve Completed</button>
          <button v-if="selectedOrder.status === 'needs_review'" type="button" class="rounded-xl bg-amber-600 hover:bg-amber-500 px-3.5 py-2 text-xs font-bold text-white" @click="resolveReview(selectedOrder.id, 'mark_failed')">Resolve Failed</button>
          <button v-if="selectedOrder.payment_status === 'paid'" type="button" class="rounded-xl bg-red-600 hover:bg-red-500 px-3.5 py-2 text-xs font-bold text-white" @click="refundOrder(selectedOrder.id)">Issue Refund</button>
        </div>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-2 gap-4 text-xs">
        <div class="rounded-2xl bg-[#070B16] border border-white/10 p-4 space-y-2">
          <p class="font-bold text-sky-400">Immutable Status Transitions</p>
          <div v-for="h in selectedOrder.status_history || []" :key="h.id" class="text-slate-300 border-l-2 border-sky-500/40 pl-3 py-0.5">
            <span class="text-slate-400">{{ h.from_status || 'init' }}</span> → <span class="text-white font-bold uppercase">{{ h.to_status }}</span>
            <p class="text-[11px] text-slate-400">{{ h.reason }}</p>
          </div>
        </div>
        <div class="rounded-2xl bg-[#070B16] border border-white/10 p-4 space-y-2">
          <p class="font-bold text-sky-400">Supplier Fulfillment Attempts</p>
          <div v-if="!selectedOrder.fulfillment?.attempts?.length" class="text-slate-400">No supplier attempts recorded yet.</div>
          <div v-for="a in selectedOrder.fulfillment?.attempts || []" :key="a.id" class="rounded-xl bg-white/[0.03] border border-white/10 p-2.5 text-slate-300">
            <span class="font-mono font-bold text-white">#{{ a.attempt_number }}</span> [{{ a.action }}] → <span class="font-bold text-sky-300 uppercase">{{ a.outcome }}</span> ({{ a.duration_ms }}ms)
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
