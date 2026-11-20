<script setup lang="ts">
import { formatMoney } from '@topup/shared-types';

const { request } = useAdminApi();
const toast = useAdminToast();

const suppliers = ref<any[]>([]);
const loading = ref(true);
const checkingId = ref<string | null>(null);
const savingId = ref<string | null>(null);
const priorityDrafts = ref<Record<string, number>>({});

const summary = computed(() => ({
  total: suppliers.value.length,
  healthy: suppliers.value.filter((s) => s.health_status === 'healthy' && !s.is_paused && !s.is_maintenance).length,
  paused: suppliers.value.filter((s) => s.is_paused || s.is_maintenance).length,
}));

async function loadSuppliers() {
  loading.value = true;
  try {
    const res = await request<{ data: any[] }>('/admin/suppliers');
    suppliers.value = res.data;
    for (const s of res.data) {
      priorityDrafts.value[s.id] = Number(s.priority ?? 100);
    }
  } catch (e: any) {
    toast.error(e?.data?.message || 'Failed to load suppliers.');
  } finally {
    loading.value = false;
  }
}

async function updateSupplier(s: any, payload: Record<string, any>, successMsg: string) {
  savingId.value = s.id;
  try {
    await request(`/admin/suppliers/${s.id}`, {
      method: 'PATCH',
      body: payload,
    });
    toast.success(successMsg);
    await loadSuppliers();
  } catch (e: any) {
    toast.error(e?.data?.message || 'Supplier update failed.');
  } finally {
    savingId.value = null;
  }
}

async function savePriority(s: any) {
  const priority = Number(priorityDrafts.value[s.id]);
  if (!Number.isFinite(priority) || priority < 1 || priority > 1000) {
    toast.error('Priority must be between 1 and 1000.');
    return;
  }
  await updateSupplier(s, { priority }, `${s.name} priority updated to #${priority}.`);
}

async function togglePause(s: any) {
  const next = !s.is_paused;
  await updateSupplier(s, { is_paused: next }, `${s.name} ${next ? 'paused' : 'resumed'}.`);
}

async function toggleMaintenance(s: any) {
  const next = !s.is_maintenance;
  await updateSupplier(s, { is_maintenance: next }, `${s.name} maintenance ${next ? 'enabled' : 'disabled'}.`);
}

async function runHealthCheck(s: any) {
  checkingId.value = s.id;
  try {
    const res = await request<{ data: { health_check?: any; balance_snapshot?: any } }>(
      `/admin/suppliers/${s.id}/health-check`,
      { method: 'POST' },
    );
    const latency = res.data?.health_check?.latency_ms;
    toast.success(`${s.name} health & balance refreshed${latency != null ? ` (${latency}ms)` : ''}.`);
    await loadSuppliers();
  } catch (e: any) {
    toast.error(e?.data?.message || `Health check failed for ${s.name}.`);
  } finally {
    checkingId.value = null;
  }
}

function healthBadgeClass(status: string) {
  if (status === 'healthy') return 'bg-emerald-500/15 text-emerald-300 border-emerald-500/30';
  if (status === 'degraded') return 'bg-amber-500/15 text-amber-300 border-amber-500/30';
  return 'bg-red-500/15 text-red-300 border-red-500/30';
}

function capabilityList(cap: any) {
  if (!cap) return [];
  return [
    { label: 'Submit Order', ok: !!cap.supports_submit_order },
    { label: 'Status Lookup', ok: !!cap.supports_status_lookup },
    { label: 'Idempotency', ok: !!cap.supports_idempotency },
    { label: 'Balance Check', ok: !!cap.supports_balance_check },
    { label: 'Player Check', ok: !!cap.supports_player_validation },
  ];
}

onMounted(loadSuppliers);
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Supplier Routing & Health</h1>
        <p class="text-xs text-brand-muted mt-1">
          Monitor upstream fulfillment adapters, wallet balances, routing priority, and circuit breakers.
        </p>
      </div>
      <button
        type="button"
        class="inline-flex items-center justify-center gap-2 rounded-xl border border-brand-border bg-brand-surface px-4 py-2 text-xs font-semibold text-white hover:border-brand-accent/50 transition"
        :disabled="loading"
        @click="loadSuppliers"
      >
        <span>{{ loading ? 'Refreshing...' : 'Refresh Suppliers' }}</span>
      </button>
    </div>

    <!-- Summary Strip -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
      <div class="admin-card p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-muted font-semibold">Configured Suppliers</p>
        <p class="text-2xl font-bold text-white mt-1">{{ summary.total }}</p>
      </div>
      <div class="admin-card p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-muted font-semibold">Active & Healthy</p>
        <p class="text-2xl font-bold text-emerald-400 mt-1">{{ summary.healthy }}</p>
      </div>
      <div class="admin-card p-4">
        <p class="text-[11px] uppercase tracking-wider text-brand-muted font-semibold">Paused / Maintenance</p>
        <p class="text-2xl font-bold mt-1" :class="summary.paused > 0 ? 'text-amber-400' : 'text-white'">
          {{ summary.paused }}
        </p>
      </div>
    </div>

    <!-- Loading Skeleton -->
    <div v-if="loading && suppliers.length === 0" class="grid grid-cols-1 lg:grid-cols-3 gap-4">
      <div v-for="i in 3" :key="i" class="admin-card p-5 h-64 animate-pulse bg-brand-surface/50" />
    </div>

    <!-- Supplier Cards -->
    <div v-else class="grid grid-cols-1 xl:grid-cols-2 2xl:grid-cols-3 gap-5">
      <div
        v-for="s in suppliers"
        :key="s.id"
        class="admin-card p-5 flex flex-col justify-between gap-4 text-xs"
        :class="s.is_paused || s.is_maintenance ? 'border-amber-500/40' : ''"
      >
        <div class="space-y-4">
          <!-- Top Identity Row -->
          <div class="flex items-start justify-between gap-3">
            <div>
              <div class="flex items-center gap-2 flex-wrap">
                <h2 class="text-base font-bold text-white">{{ s.name }}</h2>
                <span class="rounded-md border border-brand-border bg-brand-bg px-2 py-0.5 text-[10px] font-mono uppercase text-brand-muted">
                  {{ s.environment }}
                </span>
              </div>
              <p class="font-mono text-[11px] text-brand-muted mt-0.5">{{ s.code }}</p>
            </div>
            <div class="flex flex-col items-end gap-1">
              <span
                class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase"
                :class="healthBadgeClass(s.health_status)"
              >
                {{ s.health_status }}
              </span>
              <span v-if="s.is_paused" class="rounded-full bg-amber-500/20 px-2 py-0.5 text-[10px] font-semibold text-amber-300">
                PAUSED
              </span>
              <span v-if="s.is_maintenance" class="rounded-full bg-purple-500/20 px-2 py-0.5 text-[10px] font-semibold text-purple-300">
                MAINTENANCE
              </span>
            </div>
          </div>

          <!-- Balance & Reliability Box -->
          <div class="grid grid-cols-2 gap-3 rounded-xl border border-brand-border/70 bg-brand-bg/70 p-3">
            <div>
              <p class="text-[10px] uppercase tracking-wider text-brand-muted">Available Balance</p>
              <p v-if="s.balance_snapshots?.[0]" class="text-sm font-bold text-brand-accent mt-1">
                {{ formatMoney(s.balance_snapshots[0].available_balance_minor, s.balance_snapshots[0].currency) }}
              </p>
              <p v-else class="text-xs text-brand-muted mt-1">Not captured</p>
            </div>
            <div>
              <div class="flex items-center justify-between">
                <p class="text-[10px] uppercase tracking-wider text-brand-muted">Reliability</p>
                <span class="font-bold text-white">{{ Number(s.reliability_score ?? 0).toFixed(1) }}%</span>
              </div>
              <div class="mt-2 h-2 w-full rounded-full bg-brand-border/60 overflow-hidden">
                <div
                  class="h-full rounded-full transition-all"
                  :class="Number(s.reliability_score) >= 95 ? 'bg-emerald-400' : Number(s.reliability_score) >= 80 ? 'bg-amber-400' : 'bg-red-400'"
                  :style="{ width: `${Math.min(100, Math.max(0, Number(s.reliability_score ?? 0)))}%` }"
                />
              </div>
            </div>
          </div>

          <!-- Capabilities -->
          <div v-if="s.capabilities" class="space-y-1.5">
            <p class="text-[10px] uppercase tracking-wider text-brand-muted font-semibold">Adapter Capabilities</p>
            <div class="flex flex-wrap gap-1.5">
              <span
                v-for="cap in capabilityList(s.capabilities)"
                :key="cap.label"
                class="rounded-lg border px-2 py-0.5 text-[10px] font-medium"
                :class="cap.ok ? 'border-emerald-500/30 bg-emerald-500/10 text-emerald-300' : 'border-brand-border bg-brand-bg text-brand-muted/60 line-through'"
              >
                {{ cap.label }}
              </span>
            </div>
          </div>

          <!-- Priority Editor -->
          <div class="flex items-center justify-between gap-2 rounded-xl border border-brand-border/70 bg-brand-bg/50 px-3 py-2">
            <label class="text-[11px] font-semibold text-brand-muted">Routing Priority (Lower = First)</label>
            <div class="flex items-center gap-1.5">
              <input
                v-model.number="priorityDrafts[s.id]"
                type="number"
                min="1"
                max="1000"
                class="w-20 rounded-lg border border-brand-border bg-brand-surface px-2.5 py-1 text-right text-xs font-mono text-white focus:border-brand-accent focus:outline-none"
              />
              <button
                type="button"
                class="rounded-lg border border-brand-accent/40 bg-brand-accent/15 px-2.5 py-1 text-[11px] font-semibold text-brand-accent hover:bg-brand-accent/25 transition"
                :disabled="savingId === s.id"
                @click="savePriority(s)"
              >
                Save
              </button>
            </div>
          </div>

          <!-- Recent Health Checks -->
          <div v-if="s.health_checks?.length" class="space-y-1">
            <p class="text-[10px] uppercase tracking-wider text-brand-muted font-semibold">Recent Probes</p>
            <div class="flex items-center gap-1.5 flex-wrap">
              <span
                v-for="hc in s.health_checks"
                :key="hc.id"
                class="rounded-md px-2 py-0.5 text-[10px] font-mono"
                :class="hc.status === 'healthy' ? 'bg-emerald-500/15 text-emerald-300' : 'bg-red-500/15 text-red-300'"
                :title="hc.checked_at"
              >
                {{ hc.latency_ms ?? 0 }}ms
              </span>
            </div>
          </div>
        </div>

        <!-- Actions Footer -->
        <div class="flex flex-wrap gap-2 pt-3 border-t border-brand-border/60">
          <button
            type="button"
            class="btn-admin-primary flex-1 rounded-xl px-3 py-2 text-xs font-semibold text-white disabled:opacity-50"
            :disabled="checkingId === s.id"
            @click="runHealthCheck(s)"
          >
            {{ checkingId === s.id ? 'Checking...' : 'Check Health & Balance' }}
          </button>
          <button
            type="button"
            class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
            :class="s.is_paused ? 'border-emerald-500/40 bg-emerald-500/15 text-emerald-300' : 'border-amber-500/40 bg-amber-500/10 text-amber-300 hover:bg-amber-500/20'"
            :disabled="savingId === s.id"
            @click="togglePause(s)"
          >
            {{ s.is_paused ? 'Resume' : 'Pause' }}
          </button>
          <button
            type="button"
            class="rounded-xl border px-3 py-2 text-xs font-semibold transition"
            :class="s.is_maintenance ? 'border-purple-400/50 bg-purple-500/20 text-purple-200' : 'border-brand-border bg-brand-bg text-brand-muted hover:text-white'"
            :disabled="savingId === s.id"
            @click="toggleMaintenance(s)"
          >
            {{ s.is_maintenance ? 'Exit Maint.' : 'Maint.' }}
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
