<script setup lang="ts">
import { formatMoney } from '@topup/shared-types';

const { request } = useAdminApi();
const toast = useAdminToast();

type OpsTab = 'promotions' | 'customers' | 'settings' | 'audit';
const activeTab = ref<OpsTab>('promotions');
const loading = ref(true);

// Data stores
const coupons = ref<any[]>([]);
const customers = ref<any[]>([]);
const auditLogs = ref<any[]>([]);

// Settings state
const exchangeRate = ref(4100);
const defaultCurrency = ref<'USD' | 'KHR'>('USD');
const globalPause = ref(false);
const savingSettings = ref(false);

// Search filters
const customerSearch = ref('');
const auditSearch = ref('');

// Create Coupon Form
const creatingCoupon = ref(false);
const showCouponForm = ref(false);
const couponForm = reactive({
  code: '',
  name_en: '',
  name_km: '',
  discount_type: 'percent' as 'percent' | 'fixed_minor',
  discount_value: 10,
  currency: 'USD' as 'USD' | 'KHR',
  min_order_minor: 0,
  max_discount_minor: 500,
  max_total_uses: 100,
  is_active: true,
});

const filteredCustomers = computed(() => {
  const q = customerSearch.value.trim().toLowerCase();
  if (!q) return customers.value;
  return customers.value.filter((u) => {
    return (
      (u.name || '').toLowerCase().includes(q) ||
      (u.email || '').toLowerCase().includes(q) ||
      (u.phone || '').toLowerCase().includes(q)
    );
  });
});

const filteredAuditLogs = computed(() => {
  const q = auditSearch.value.trim().toLowerCase();
  if (!q) return auditLogs.value;
  return auditLogs.value.filter((log) => {
    return (
      (log.action || '').toLowerCase().includes(q) ||
      (log.target_type || '').toLowerCase().includes(q) ||
      (log.target_id || '').toLowerCase().includes(q) ||
      (log.actor_type || '').toLowerCase().includes(q)
    );
  });
});

async function loadAll() {
  loading.value = true;
  try {
    const [cRes, uRes, aRes, sRes] = await Promise.all([
      request<{ data: any[] }>('/admin/promotions/coupons'),
      request<{ data: any[] }>('/admin/customers'),
      request<{ data: any[] }>('/admin/audit-logs'),
      request<{ data: any[] }>('/admin/settings'),
    ]);

    coupons.value = cRes.data;
    customers.value = uRes.data;
    auditLogs.value = aRes.data;

    const fx = sRes.data.find((x: any) => x.key === 'exchange_rate_usd_to_khr');
    if (fx?.value?.v !== undefined) exchangeRate.value = Number(fx.value.v);

    const defCur = sRes.data.find((x: any) => x.key === 'default_currency');
    if (defCur?.value?.v === 'USD' || defCur?.value?.v === 'KHR') {
      defaultCurrency.value = defCur.value.v;
    }

    const pause = sRes.data.find((x: any) => x.key === 'fulfillment_global_pause');
    if (pause?.value?.v !== undefined) globalPause.value = Boolean(pause.value.v);
  } catch (e: any) {
    toast.error(e?.data?.message || 'Failed to load operations data.');
  } finally {
    loading.value = false;
  }
}

async function createCoupon() {
  if (!couponForm.code.trim() || !couponForm.name_en.trim() || !couponForm.name_km.trim()) {
    toast.error('Please fill in coupon code, English name, and Khmer name.');
    return;
  }
  creatingCoupon.value = true;
  try {
    await request('/admin/promotions/coupons', {
      method: 'POST',
      body: {
        code: couponForm.code.trim().toUpperCase(),
        name_en: couponForm.name_en.trim(),
        name_km: couponForm.name_km.trim(),
        discount_type: couponForm.discount_type,
        discount_value: couponForm.discount_type === 'percent'
          ? Math.round(Number(couponForm.discount_value) * 100)
          : Number(couponForm.discount_value),
        currency: couponForm.currency,
        min_order_minor: Number(couponForm.min_order_minor) || 0,
        max_discount_minor: couponForm.max_discount_minor ? Number(couponForm.max_discount_minor) : null,
        max_total_uses: couponForm.max_total_uses ? Number(couponForm.max_total_uses) : null,
        is_active: couponForm.is_active,
      },
    });
    toast.success(`Coupon ${couponForm.code.trim().toUpperCase()} created!`);
    couponForm.code = '';
    couponForm.name_en = '';
    couponForm.name_km = '';
    showCouponForm.value = false;
    await loadAll();
  } catch (e: any) {
    toast.error(e?.data?.message || 'Failed to create coupon.');
  } finally {
    creatingCoupon.value = false;
  }
}

async function saveSettings() {
  savingSettings.value = true;
  try {
    await request('/admin/settings', {
      method: 'PATCH',
      body: {
        exchange_rate_usd_to_khr: Number(exchangeRate.value),
        default_currency: defaultCurrency.value,
        fulfillment_global_pause: globalPause.value,
      },
    });
    toast.success('Platform settings saved & recorded in immutable audit log.');
    await loadAll();
  } catch (e: any) {
    toast.error(e?.data?.message || 'Failed to save settings.');
  } finally {
    savingSettings.value = false;
  }
}

function formatCouponDiscount(c: any) {
  if (c.discount_type === 'percent') {
    const pct = Number(c.discount_value) / 100;
    return `${Number.isInteger(pct) ? pct : pct.toFixed(2)}% OFF`;
  }
  return `${formatMoney(c.discount_value, c.currency || 'USD')} OFF`;
}

onMounted(loadAll);
</script>

<template>
  <div class="space-y-6">
    <!-- Header -->
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
      <div>
        <h1 class="text-2xl font-bold text-white tracking-tight">Operations Hub</h1>
        <p class="text-xs text-brand-muted mt-1">
          Manage promo campaigns, customer accounts, global FX & safety settings, and immutable audit trails.
        </p>
      </div>
      <button
        type="button"
        class="rounded-xl border border-brand-border bg-brand-surface px-4 py-2 text-xs font-semibold text-white hover:border-brand-accent/50 transition self-start sm:self-auto"
        :disabled="loading"
        @click="loadAll"
      >
        {{ loading ? 'Syncing...' : 'Refresh All' }}
      </button>
    </div>

    <!-- 4-Tab Bar (Single Swipeable Row on Mobile) -->
    <div class="flex items-center gap-2 overflow-x-auto no-scrollbar whitespace-nowrap border-b border-brand-border pb-3 -mx-1 px-1">
      <button
        type="button"
        class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-semibold transition"
        :class="activeTab === 'promotions' ? 'btn-admin-primary text-white' : 'text-brand-muted hover:text-white hover:bg-white/5'"
        @click="activeTab = 'promotions'"
      >
        Promotions
        <span class="ml-1.5 rounded-full bg-black/25 px-2 py-0.5 text-[10px]">{{ coupons.length }}</span>
      </button>
      <button
        type="button"
        class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-semibold transition"
        :class="activeTab === 'customers' ? 'btn-admin-primary text-white' : 'text-brand-muted hover:text-white hover:bg-white/5'"
        @click="activeTab = 'customers'"
      >
        Customers
        <span class="ml-1.5 rounded-full bg-black/25 px-2 py-0.5 text-[10px]">{{ customers.length }}</span>
      </button>
      <button
        type="button"
        class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-semibold transition"
        :class="activeTab === 'settings' ? 'btn-admin-primary text-white' : 'text-brand-muted hover:text-white hover:bg-white/5'"
        @click="activeTab = 'settings'"
      >
        Settings
      </button>
      <button
        type="button"
        class="shrink-0 rounded-xl px-3.5 py-2 text-xs font-semibold transition"
        :class="activeTab === 'audit' ? 'btn-admin-primary text-white' : 'text-brand-muted hover:text-white hover:bg-white/5'"
        @click="activeTab = 'audit'"
      >
        Audit Logs
        <span class="ml-1.5 rounded-full bg-black/25 px-2 py-0.5 text-[10px]">{{ auditLogs.length }}</span>
      </button>
    </div>

    <!-- TAB 1: Promotions -->
    <div v-if="activeTab === 'promotions'" class="space-y-5">
      <div class="flex items-center justify-between">
        <h2 class="text-sm font-bold text-white">Promo Codes & Coupons</h2>
        <button
          type="button"
          class="btn-admin-primary rounded-xl px-4 py-2 text-xs font-bold text-white"
          @click="showCouponForm = !showCouponForm"
        >
          {{ showCouponForm ? 'Close Form' : '+ Create Coupon' }}
        </button>
      </div>

      <!-- Create Coupon Panel -->
      <form
        v-if="showCouponForm"
        class="admin-card p-5 space-y-4 border-brand-accent/40"
        @submit.prevent="createCoupon"
      >
        <div class="flex items-center justify-between">
          <h3 class="text-sm font-bold text-white">New Promo Coupon</h3>
          <span class="text-[11px] text-brand-muted">Amounts in minor units (USD cents / KHR riel)</span>
        </div>

        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 text-xs">
          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Coupon Code *</label>
            <input
              v-model="couponForm.code"
              type="text"
              required
              placeholder="e.g. VANN15"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 font-mono uppercase text-white focus:border-brand-accent focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Campaign Name (EN) *</label>
            <input
              v-model="couponForm.name_en"
              type="text"
              required
              placeholder="Weekend Diamond Boost"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 text-white focus:border-brand-accent focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Campaign Name (KM) *</label>
            <input
              v-model="couponForm.name_km"
              type="text"
              required
              placeholder="ប្រូម៉ូសិនចុងសប្ដាហ៍"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 text-white focus:border-brand-accent focus:outline-none"
            />
          </div>

          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Discount Type</label>
            <select
              v-model="couponForm.discount_type"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 text-white focus:border-brand-accent focus:outline-none"
            >
              <option value="percent">Percentage (%)</option>
              <option value="fixed_minor">Fixed Minor Amount</option>
            </select>
          </div>
          <div>
            <label class="block text-brand-muted mb-1 font-semibold">
              {{ couponForm.discount_type === 'percent' ? 'Percent (1–100)' : 'Discount (Minor Units)' }}
            </label>
            <input
              v-model.number="couponForm.discount_value"
              type="number"
              min="1"
              required
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 font-mono text-white focus:border-brand-accent focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Currency</label>
            <select
              v-model="couponForm.currency"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 text-white focus:border-brand-accent focus:outline-none"
            >
              <option value="USD">USD</option>
              <option value="KHR">KHR</option>
            </select>
          </div>

          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Min Order (Minor)</label>
            <input
              v-model.number="couponForm.min_order_minor"
              type="number"
              min="0"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 font-mono text-white focus:border-brand-accent focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Max Discount Cap (Minor)</label>
            <input
              v-model.number="couponForm.max_discount_minor"
              type="number"
              min="1"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 font-mono text-white focus:border-brand-accent focus:outline-none"
            />
          </div>
          <div>
            <label class="block text-brand-muted mb-1 font-semibold">Max Total Uses</label>
            <input
              v-model.number="couponForm.max_total_uses"
              type="number"
              min="1"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3 py-2 font-mono text-white focus:border-brand-accent focus:outline-none"
            />
          </div>
        </div>

        <div class="flex items-center justify-end gap-3 pt-2">
          <button
            type="button"
            class="rounded-xl border border-brand-border px-4 py-2 text-xs text-brand-muted hover:text-white"
            @click="showCouponForm = false"
          >
            Cancel
          </button>
          <button
            type="submit"
            class="btn-admin-primary rounded-xl px-5 py-2 text-xs font-bold text-white disabled:opacity-50"
            :disabled="creatingCoupon"
          >
            {{ creatingCoupon ? 'Creating...' : 'Create Coupon' }}
          </button>
        </div>
      </form>

      <!-- Coupons Grid -->
      <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-3 gap-4 text-xs">
        <div
          v-for="c in coupons"
          :key="c.id"
          class="admin-card p-4 space-y-3"
        >
          <div class="flex items-start justify-between gap-2">
            <div>
              <p class="font-mono text-base font-bold text-brand-accent tracking-wide">{{ c.code }}</p>
              <p class="text-white font-semibold mt-0.5">{{ c.name_en }}</p>
              <p class="text-brand-muted text-[11px]">{{ c.name_km }}</p>
            </div>
            <span
              class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase"
              :class="c.is_active ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300' : 'border-slate-500/30 bg-slate-500/15 text-slate-300'"
            >
              {{ c.is_active ? 'Active' : 'Inactive' }}
            </span>
          </div>

          <div class="flex items-center justify-between rounded-xl bg-brand-bg/70 border border-brand-border/60 px-3 py-2">
            <span class="font-bold text-emerald-400">{{ formatCouponDiscount(c) }}</span>
            <span class="text-brand-muted">
              Used <strong class="text-white">{{ c.used_count }}</strong> / {{ c.max_total_uses || '∞' }}
            </span>
          </div>
        </div>
      </div>
    </div>

    <!-- TAB 2: Customers -->
    <div v-else-if="activeTab === 'customers'" class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <input
          v-model="customerSearch"
          type="text"
          placeholder="Search customer by name, email, or phone..."
          class="w-full max-w-md rounded-xl border border-brand-border bg-brand-surface px-3.5 py-2 text-xs text-white placeholder:text-brand-muted focus:border-brand-accent focus:outline-none"
        />
        <span class="text-xs text-brand-muted">Showing {{ filteredCustomers.length }} account(s)</span>
      </div>

      <!-- Mobile Customers Cards -->
      <div class="md:hidden space-y-3">
        <div v-if="filteredCustomers.length === 0" class="admin-card rounded-2xl p-6 text-center text-xs text-brand-muted">
          No matching users found.
        </div>
        <div v-for="u in filteredCustomers" :key="u.id" class="admin-card rounded-2xl p-4 space-y-3 text-xs">
          <div class="flex items-center justify-between gap-2">
            <div class="flex items-center gap-3 min-w-0">
              <div class="flex h-9 w-9 shrink-0 items-center justify-center rounded-full bg-brand-primary/20 border border-brand-accent/30 text-xs font-bold text-brand-accent uppercase">
                {{ (u.name || u.email || '?').charAt(0) }}
              </div>
              <div class="min-w-0">
                <p class="font-semibold text-white truncate">{{ u.name }}</p>
                <p class="text-[11px] text-brand-muted truncate">{{ u.email }}</p>
              </div>
            </div>
            <span
              class="shrink-0 rounded-full border px-2 py-0.5 text-[10px] font-bold uppercase"
              :class="u.auth_provider === 'google' ? 'border-sky-500/30 bg-sky-500/15 text-sky-300' : 'border-brand-border bg-brand-bg text-brand-muted'"
            >
              {{ u.auth_provider || 'email' }}
            </span>
          </div>
          <div class="flex items-center justify-between gap-2 pt-2 border-t border-white/5 text-[11px]">
            <div class="flex flex-wrap gap-1">
              <span v-for="r in (u.roles || [])" :key="r.id" class="rounded-md bg-brand-primary/15 border border-brand-primary/30 px-2 py-0.5 text-[10px] font-mono text-brand-accent">
                {{ r.slug }}
              </span>
              <span v-if="!u.roles?.length" class="text-brand-muted">customer</span>
            </div>
            <span class="text-brand-muted">Orders: <strong class="text-white">{{ u.orders_count ?? 0 }}</strong></span>
          </div>
        </div>
      </div>

      <!-- Desktop Customers Table -->
      <div class="hidden md:block admin-card rounded-2xl overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-brand-border text-[11px] text-brand-muted uppercase tracking-wider">
            <tr>
              <th class="p-3.5">User</th>
              <th class="p-3.5">Auth Provider</th>
              <th class="p-3.5">Roles</th>
              <th class="p-3.5 text-center">Orders</th>
              <th class="p-3.5">Status</th>
              <th class="p-3.5">Joined</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-brand-border/60">
            <tr v-if="filteredCustomers.length === 0">
              <td colspan="6" class="p-8 text-center text-brand-muted">No matching users found.</td>
            </tr>
            <tr v-for="u in filteredCustomers" :key="u.id" class="hover:bg-white/[0.02]">
              <td class="p-3.5">
                <div class="flex items-center gap-3">
                  <div class="flex h-8 w-8 shrink-0 items-center justify-center rounded-full bg-brand-primary/20 border border-brand-accent/30 text-xs font-bold text-brand-accent uppercase">
                    {{ (u.name || u.email || '?').charAt(0) }}
                  </div>
                  <div>
                    <p class="font-semibold text-white">{{ u.name }}</p>
                    <p class="text-[11px] text-brand-muted">{{ u.email }}</p>
                  </div>
                </div>
              </td>
              <td class="p-3.5">
                <span
                  class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase"
                  :class="u.auth_provider === 'google' ? 'border-sky-500/30 bg-sky-500/15 text-sky-300' : 'border-brand-border bg-brand-bg text-brand-muted'"
                >
                  {{ u.auth_provider || 'email' }}
                </span>
              </td>
              <td class="p-3.5">
                <div class="flex flex-wrap gap-1">
                  <span
                    v-for="r in (u.roles || [])"
                    :key="r.id"
                    class="rounded-md bg-brand-primary/15 border border-brand-primary/30 px-2 py-0.5 text-[10px] font-mono text-brand-accent"
                  >
                    {{ r.slug }}
                  </span>
                  <span v-if="!u.roles?.length" class="text-brand-muted">customer</span>
                </div>
              </td>
              <td class="p-3.5 text-center font-mono font-bold text-white">
                {{ u.orders_count ?? 0 }}
              </td>
              <td class="p-3.5">
                <span
                  class="rounded-full border px-2.5 py-0.5 text-[10px] font-bold uppercase"
                  :class="u.status === 'active' || !u.status ? 'border-emerald-500/30 bg-emerald-500/15 text-emerald-300' : 'border-red-500/30 bg-red-500/15 text-red-300'"
                >
                  {{ u.status || 'active' }}
                </span>
              </td>
              <td class="p-3.5 text-brand-muted">
                {{ new Date(u.created_at).toLocaleDateString() }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>

    <!-- TAB 3: Platform Settings -->
    <div v-else-if="activeTab === 'settings'" class="max-w-2xl space-y-5">
      <div class="admin-card p-6 space-y-5">
        <div>
          <h2 class="text-base font-bold text-white">Global Platform Controls</h2>
          <p class="text-xs text-brand-muted mt-0.5">
            Changes take effect immediately across pricing quotes and fulfillment workers and are permanently audited.
          </p>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 text-xs">
          <div>
            <label class="block text-brand-muted mb-1.5 font-semibold">Exchange Rate (1 USD → KHR)</label>
            <input
              v-model.number="exchangeRate"
              type="number"
              min="3000"
              max="6000"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3.5 py-2.5 text-sm font-mono text-white focus:border-brand-accent focus:outline-none"
            />
            <p class="text-[11px] text-brand-muted mt-1">Allowed range: 3,000 – 6,000 KHR</p>
          </div>

          <div>
            <label class="block text-brand-muted mb-1.5 font-semibold">Default Storefront Currency</label>
            <select
              v-model="defaultCurrency"
              class="w-full rounded-xl border border-brand-border bg-brand-bg px-3.5 py-2.5 text-sm text-white focus:border-brand-accent focus:outline-none"
            >
              <option value="USD">USD ($)</option>
              <option value="KHR">KHR (៛)</option>
            </select>
            <p class="text-[11px] text-brand-muted mt-1">Default pricing display for new guests</p>
          </div>
        </div>

        <!-- Global Pause Switch -->
        <div
          class="flex items-center justify-between gap-4 rounded-xl border p-4 transition"
          :class="globalPause ? 'border-red-500/50 bg-red-500/10' : 'border-brand-border bg-brand-bg/70'"
        >
          <div>
            <p class="text-xs font-bold text-white">Emergency Global Fulfillment Pause</p>
            <p class="text-[11px] text-brand-muted mt-0.5">
              When enabled, paid orders are held safely in queue without dispatching to upstream suppliers.
            </p>
          </div>
          <button
            type="button"
            role="switch"
            :aria-checked="globalPause"
            class="relative inline-flex h-6 w-11 shrink-0 cursor-pointer rounded-full border-2 border-transparent transition-colors"
            :class="globalPause ? 'bg-red-500' : 'bg-slate-600'"
            @click="globalPause = !globalPause"
          >
            <span
              class="pointer-events-none inline-block h-5 w-5 transform rounded-full bg-white shadow transition"
              :class="globalPause ? 'translate-x-5' : 'translate-x-0'"
            />
          </button>
        </div>

        <div class="flex justify-end pt-2">
          <button
            type="button"
            class="btn-admin-primary rounded-xl px-5 py-2.5 text-xs font-bold text-white disabled:opacity-50"
            :disabled="savingSettings"
            @click="saveSettings"
          >
            {{ savingSettings ? 'Saving Settings...' : 'Save Platform Settings' }}
          </button>
        </div>
      </div>
    </div>

    <!-- TAB 4: Audit Logs -->
    <div v-else class="space-y-4">
      <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
        <input
          v-model="auditSearch"
          type="text"
          placeholder="Search audit logs by action, target, or actor..."
          class="w-full max-w-md rounded-xl border border-brand-border bg-brand-surface px-3.5 py-2 text-xs text-white placeholder:text-brand-muted focus:border-brand-accent focus:outline-none"
        />
        <span class="text-xs text-brand-muted">{{ filteredAuditLogs.length }} event(s)</span>
      </div>

      <!-- Mobile Audit Log Cards -->
      <div class="md:hidden space-y-2.5">
        <div v-if="filteredAuditLogs.length === 0" class="admin-card rounded-2xl p-6 text-center text-xs text-brand-muted">
          No audit logs recorded.
        </div>
        <div v-for="log in filteredAuditLogs" :key="log.id" class="admin-card rounded-2xl p-3.5 space-y-2 text-xs">
          <div class="flex items-center justify-between gap-2">
            <span class="rounded-md bg-brand-primary/15 border border-brand-primary/30 px-2 py-0.5 font-mono text-[11px] font-bold text-brand-accent truncate">
              {{ log.action }}
            </span>
            <span class="text-[10px] text-brand-muted shrink-0">{{ new Date(log.created_at).toLocaleString() }}</span>
          </div>
          <div class="flex items-center justify-between gap-2 text-[11px] text-brand-muted">
            <span class="font-mono truncate">{{ log.target_type }} {{ log.target_id }}</span>
            <span class="font-mono shrink-0">{{ log.ip_address || '-' }}</span>
          </div>
        </div>
      </div>

      <!-- Desktop Audit Logs Table -->
      <div class="hidden md:block admin-card rounded-2xl overflow-x-auto">
        <table class="w-full text-left text-xs">
          <thead class="border-b border-brand-border text-[11px] text-brand-muted uppercase tracking-wider">
            <tr>
              <th class="p-3.5">Action</th>
              <th class="p-3.5">Actor</th>
              <th class="p-3.5">Target</th>
              <th class="p-3.5">IP Address</th>
              <th class="p-3.5 text-right">Timestamp</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-brand-border/60">
            <tr v-if="filteredAuditLogs.length === 0">
              <td colspan="5" class="p-8 text-center text-brand-muted">No audit logs recorded.</td>
            </tr>
            <tr v-for="log in filteredAuditLogs" :key="log.id" class="hover:bg-white/[0.02]">
              <td class="p-3.5">
                <span class="rounded-md bg-brand-primary/15 border border-brand-primary/30 px-2 py-0.5 font-mono font-bold text-brand-accent">
                  {{ log.action }}
                </span>
              </td>
              <td class="p-3.5 text-slate-200">
                <span class="uppercase text-[10px] font-bold text-brand-muted mr-1">{{ log.actor_type || 'system' }}</span>
                <span class="font-mono text-[11px]">{{ log.actor_id ? String(log.actor_id).slice(0, 8) : '-' }}</span>
              </td>
              <td class="p-3.5 font-mono text-[11px] text-brand-muted">
                {{ log.target_type }} <span class="text-slate-300">{{ log.target_id }}</span>
              </td>
              <td class="p-3.5 font-mono text-[11px] text-brand-muted">
                {{ log.ip_address || '-' }}
              </td>
              <td class="p-3.5 text-right text-brand-muted whitespace-nowrap">
                {{ new Date(log.created_at).toLocaleString() }}
              </td>
            </tr>
          </tbody>
        </table>
      </div>
    </div>
  </div>
</template>
