<script setup lang="ts">
import { formatMoney } from '@topup/shared-types';

const { request } = useAdminApi();
const toast = useAdminToast();

const activeTab = ref<'discrepancies' | 'payments'>('discrepancies');
const discrepancies = ref<any[]>([]);
const payments = ref<any[]>([]);
const loading = ref(true);
const scanning = ref(false);
const statusFilter = ref<'all' | 'open' | 'investigating' | 'resolved' | 'dismissed'>('all');
const paymentSearch = ref('');

// Inline resolution form state
const activeResolveId = ref<string | null>(null);
const resolveStatus = ref<'investigating' | 'resolved' | 'dismissed'>('resolved');
const resolveNotes = ref('');
const resolving = ref(false);

const filteredDiscrepancies = computed(() => {
  if (statusFilter.value === 'all') return discrepancies.value;
  return discrepancies.value.filter((d) => d.resolution_status === statusFilter.value);
});

const filteredPayments = computed(() => {
  const q = paymentSearch.value.trim().toLowerCase();
  if (!q) return payments.value;
  return payments.value.filter((p) => {
    const orderNum = (p.order?.order_number || '').toLowerCase();
    const provider = (p.provider || '').toLowerCase();
    const status = (p.status || '').toLowerCase();
    const extRef = (p.provider_reference || '').toLowerCase();
    return orderNum.includes(q) || provider.includes(q) || status.includes(q) || extRef.includes(q);
  });
});

const stats = computed(() => ({
  open: discrepancies.value.filter((d) => d.resolution_status === 'open' || d.resolution_status === 'investigating').length,
  resolved: discrepancies.value.filter((d) => d.resolution_status === 'resolved' || d.resolution_status === 'dismissed').length,
  paymentsTotal: payments.value.length,
  paymentsPaid: payments.value.filter((p) => p.status === 'paid' || p.status === 'succeeded').length,
}));

async function loadData() {
  loading.value = true;
  try {
    const [dRes, pRes] = await Promise.all([
      request<{ data: any[] }>('/admin/reconciliation/discrepancies'),
      request<{ data: any[] }>('/admin/payments'),
    ]);
    discrepancies.value = dRes.data;
    payments.value = pRes.data;
  } catch (e: any) {
    toast.error(e?.data?.message || 'Failed to load reconciliation data.');
  } finally {
    loading.value = false;
  }
}

async function runScan() {
  scanning.value = true;
  try {
    const res = await request<{ data: { new_discrepancies_count: number } }>(
      '/admin/reconciliation/run',
      { method: 'POST' },
    );
    const count = res.data?.new_discrepancies_count ?? 0;
    if (count > 0) {
      toast.info(`Scan completed: ${count} new discrepancy(ies) detected.`);
    } else {
      toast.success('Scan completed: All ledgers & fulfillments reconciled cleanly.');
    }
    await loadData();
  } catch (e: any) {
    toast.error(e?.data?.message || 'Reconciliation scan failed.');
  } finally {
    scanning.value = false;
  }
}

function openResolve(d: any) {
  activeResolveId.value = d.id;
  resolveStatus.value = 'resolved';
  resolveNotes.value = d.operator_notes || '';
}

async function submitResolve(id: string) {
  if (resolveNotes.value.trim().length < 3) {
    toast.error('Please enter at least 3 characters of operator notes.');
    return;
  }
  resolving.value = true;
  try {
    await request(`/admin/reconciliation/discrepancies/${id}`, {
      method: 'PATCH',
      body: {
        resolution_status: resolveStatus.value,
        operator_notes: resolveNotes.value.trim(),
      },
    });
    toast.success(`Discrepancy marked as ${resolveStatus.value}.`);
    activeResolveId.value = null;
    resolveNotes.value = '';
    await loadData();
  } catch (e: any) {
    toast.error(e?.data?.message || 'Failed to update discrepancy.');
  } finally {
    resolving.value = false;
  }
}

function severityClass(sev: string) {
  if (sev === 'critical' || sev === 'high') return 'bg-red-500/15 text-red-300 border-red-500/30';
  if (sev === 'medium') return 'bg-amber-500/15 text-amber-300 border-amber-500/30';
  return 'bg-sky-500/15 text-sky-300 border-sky-500/30';
}

function resolutionClass(status: string) {
  if (status === 'resolved') return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
  if (status === 'investigating') return 'bg-amber-500/15 text-amber-300 border-amber-500/30';
  if (status === 'dismissed') return 'bg-slate-500/20 text-slate-300 border-slate-500/30';
  return 'bg-red-500/15 text-red-300 border-red-500/30';
}

function paymentStatusClass(status: string) {
  if (status === 'paid' || status === 'succeeded') return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
  if (status === 'pending' || status === 'processing') return 'bg-amber-500/15 text-amber-300 border-amber-500/30';
  return 'bg-red-500/15 text-red-300 border-red-500/30';
}

onMounted(loadData);
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Financial & Fulfillment Reconciliation</h1>
        <p class="text-xs text-brand-muted mt-1">
          Detect paid-unfulfilled orders, verify ABA PayWay / KHQR payment intents, and maintain ledger integrity.
        </p>
      </div>
      <div class="flex items-center gap-2">
        <button
          type="button"
          class="rounded-xl border border-brand-border bg-brand-surface px-3.5 py-2 text-xs font-semibold text-white hover:border-brand-accent/50 transition"
          :disabled="loading"
          @click="loadData"
        >
          Refresh
        </button>
        <button
          type="button"
          class="btn-admin-primary rounded-xl px-4 py-2 text-xs font-bold text-white disabled:opacity-50"
          :disabled="scanning"
          @click="runScan"
        >
          {{ scanning ? 'Scanning Ledgers...' : 'Run Reconciliation Scan' }}
        </button>
      </div>
    </div>

    <!-- KPI Cards -->
    <div class="grid grid-cols-2 lg:grid-cols-4 gap-4">
      <div class="admin-card p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-muted font-semibold">Open Discrepancies</p>
        <p class="text-2xl font-bold mt-1" :class="stats.open > 0 ? 'text-red-400' : 'text-emerald-400'">
          {{ stats.open }}
        </p>
      </div>
      <div class="admin-card p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-muted font-semibold">Resolved / Dismissed</p>
        <p class="text-2xl font-bold text-white mt-1">{{ stats.resolved }}</p>
      </div>
      <div class="admin-card p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-muted font-semibold">Recent Payment Intents</p>
        <p class="text-2xl font-bold text-white mt-1">{{ stats.paymentsTotal }}</p>
      </div>
      <div class="admin-card p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-muted font-semibold">Verified Paid</p>
        <p class="text-2xl font-bold text-brand-accent mt-1">{{ stats.paymentsPaid }}</p>
      </div>
    </div>

    <!-- Tab Bar (Single Swipeable Row on Mobile) -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar whitespace-nowrap border-b border-brand-border pb-3 -mx-1 px-1">
      <button
        type="button"
        class="shrink-0 rounded-xl px-4 py-2 text-xs font-semibold transition"
        :class="activeTab === 'discrepancies' ? 'btn-admin-primary text-white' : 'text-brand-muted hover:text-white hover:bg-white/5'"
        @click="activeTab = 'discrepancies'"
      >
        Discrepancies
        <span class="ml-1.5 rounded-full bg-black/25 px-2 py-0.5 text-[10px]">{{ discrepancies.length }}</span>
      </button>
      <button
        type="button"
        class="shrink-0 rounded-xl px-4 py-2 text-xs font-semibold transition"
        :class="activeTab === 'payments' ? 'btn-admin-primary text-white' : 'text-brand-muted hover:text-white hover:bg-white/5'"
        @click="activeTab = 'payments'"
      >
        Payment Transactions
        <span class="ml-1.5 rounded-full bg-black/25 px-2 py-0.5 text-[10px]">{{ payments.length }}</span>
      </button>
    </div>

    <!-- TAB 1: Discrepancies -->
    <div v-if="activeTab === 'discrepancies'" class="space-y-4">
      <div class="flex items-center gap-2 overflow-x-auto no-scrollbar whitespace-nowrap -mx-1 px-1">
        <button
          v-for="st in (['all', 'open', 'investigating', 'resolved', 'dismissed'] as const)"
          :key="st"
          type="button"
          class="shrink-0 rounded-lg border px-3 py-1.5 text-[11px] font-semibold capitalize transition"
          :class="statusFilter === st ? 'border-brand-accent bg-brand-accent/15 text-brand-accent' : 'border-brand-border bg-brand-surface text-brand-muted hover:text-white'"
          @click="statusFilter = st"
        >
          {{ st }}
        </button>
      </div>

      <div class="admin-card overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-brand-border text-[11px] text-brand-muted uppercase tracking-wider">
            <tr>
              <th class="p-3.5">Classification</th>
              <th class="p-3.5">Severity</th>
              <th class="p-3.5">Order</th>
              <th class="p-3.5">Summary & Notes</th>
              <th class="p-3.5">Status</th>
              <th class="p-3.5 text-right">Action</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-brand-border/60">
            <tr v-if="filteredDiscrepancies.length === 0">
              <td colspan="6" class="p-8 text-center text-brand-muted">
                No discrepancies matching this filter.
              </td>
            </tr>
            <template v-for="d in filteredDiscrepancies" :key="d.id">
              <tr class="hover:bg-white/[0.02]">
                <td class="p-3.5 font-mono font-semibold text-white">{{ d.classification }}</td>
                <td class="p-3.5">
                  <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase" :class="severityClass(d.severity)">
                    {{ d.severity }}
                  </span>
                </td>
                <td class="p-3.5 font-mono text-brand-accent">
                  {{ d.order?.order_number || '-' }}
                </td>
                <td class="p-3.5 max-w-md">
                  <p class="text-slate-200">{{ d.summary }}</p>
                  <p v-if="d.operator_notes" class="text-[11px] text-emerald-300/80 mt-1">
                    Note: {{ d.operator_notes }}
                  </p>
                </td>
                <td class="p-3.5">
                  <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase" :class="resolutionClass(d.resolution_status)">
                    {{ d.resolution_status }}
                  </span>
                </td>
                <td class="p-3.5 text-right">
                  <button
                    v-if="d.resolution_status !== 'resolved'"
                    type="button"
                    class="rounded-lg border border-emerald-500/40 bg-emerald-500/15 px-3 py-1.5 text-[11px] font-semibold text-emerald-300 hover:bg-emerald-500/25 transition"
                    @click="openResolve(d)"
                  >
                    Review / Resolve
                  </button>
                  <span v-else class="text-[11px] text-brand-muted">Closed</span>
                </td>
              </tr>

              <!-- Inline Operator Resolution Row -->
              <tr v-if="activeResolveId === d.id" class="bg-brand-bg/90">
                <td colspan="6" class="p-4">
                  <div class="flex flex-col md:flex-row items-stretch md:items-end gap-3">
                    <div class="w-full md:w-44">
                      <label class="block text-[11px] text-brand-muted mb-1">New Status</label>
                      <select
                        v-model="resolveStatus"
                        class="w-full rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-xs text-white focus:border-brand-accent focus:outline-none"
                      >
                        <option value="investigating">Investigating</option>
                        <option value="resolved">Resolved</option>
                        <option value="dismissed">Dismissed</option>
                      </select>
                    </div>
                    <div class="flex-1">
                      <label class="block text-[11px] text-brand-muted mb-1">Operator Audit Notes (required)</label>
                      <input
                        v-model="resolveNotes"
                        type="text"
                        placeholder="e.g. Verified ABA PayWay settlement #TXN-9981 and confirmed diamond delivery."
                        class="w-full rounded-xl border border-brand-border bg-brand-surface px-3 py-2 text-xs text-white placeholder:text-brand-muted/60 focus:border-brand-accent focus:outline-none"
                      />
                    </div>
                    <div class="flex items-center gap-2">
                      <button
                        type="button"
                        class="btn-admin-primary rounded-xl px-4 py-2 text-xs font-semibold text-white disabled:opacity-50"
                        :disabled="resolving"
                        @click="submitResolve(d.id)"
                      >
                        {{ resolving ? 'Saving...' : 'Confirm' }}
                      </button>
                      <button
                        type="button"
                        class="rounded-xl border border-brand-border px-3 py-2 text-xs text-brand-muted hover:text-white"
                        @click="activeResolveId = null"
                      >
                        Cancel
                      </button>
                    </div>
                  </div>
                </td>
              </tr>
            </template>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 2: Payment Transactions -->
    <div v-else class="space-y-4">
      <div class="flex items-center gap-3">
        <input
          v-model="paymentSearch"
          type="text"
          placeholder="Filter by order number, provider, reference, or status..."
          class="w-full max-w-md rounded-xl border border-brand-border bg-brand-surface px-3.5 py-2 text-xs text-white placeholder:text-brand-muted focus:border-brand-accent focus:outline-none"
        />
      </div>

      <div class="admin-card overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-brand-border text-[11px] text-brand-muted uppercase tracking-wider">
            <tr>
              <th class="p-3.5">Order</th>
              <th class="p-3.5">Provider</th>
              <th class="p-3.5">Amount</th>
              <th class="p-3.5">Status</th>
              <th class="p-3.5">Reference</th>
              <th class="p-3.5">Timestamp</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-brand-border/60">
            <tr v-if="filteredPayments.length === 0">
              <td colspan="6" class="p-8 text-center text-brand-muted">No payment transactions found.</td>
            </tr>
            <tr v-for="p in filteredPayments" :key="p.id" class="hover:bg-white/[0.02]">
              <td class="p-3.5 font-mono font-semibold text-white">
                {{ p.order?.order_number || '-' }}
              </td>
              <td class="p-3.5">
                <span class="font-semibold text-slate-200">{{ p.provider }}</span>
                <span v-if="p.provider_mode" class="ml-1.5 rounded bg-brand-bg px-1.5 py-0.5 text-[10px] font-mono text-brand-muted">
                  {{ p.provider_mode }}
                </span>
              </td>
              <td class="p-3.5 font-mono font-bold text-brand-accent">
                {{ formatMoney(p.amount_minor ?? p.order?.total_minor ?? 0, p.currency || p.order?.currency || 'USD') }}
              </td>
              <td class="p-3.5">
                <span class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase" :class="paymentStatusClass(p.status)">
                  {{ p.status }}
                </span>
              </td>
              <td class="p-3.5 font-mono text-[11px] text-brand-muted">
                {{ p.provider_reference || p.id.slice(0, 12) }}
              </td>
              <td class="p-3.5 text-brand-muted">
                {{ new Date(p.verified_at || p.created_at).toLocaleString() }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
