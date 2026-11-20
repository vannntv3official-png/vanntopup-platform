<script setup lang="ts">
import { formatMoney } from '@topup/shared-types';
import { Pencil, Check, X, Wrench } from 'lucide-vue-next';

const { request } = useAdminApi();
const toast = useAdminToast();
const games = ref<any[]>([]);
const editingPkgId = ref<string | null>(null);
const editCost = ref(0);
const editSell = ref(0);

async function loadCatalog() {
  const res = await request<{ data: any[] }>('/admin/catalog/games');
  games.value = res.data;
}

async function toggleMaintenance(game: any) {
  await request(`/admin/catalog/games/${game.id}`, {
    method: 'PATCH',
    body: { is_in_maintenance: !game.is_in_maintenance },
  });
  toast.info(`${game.name} maintenance ${!game.is_in_maintenance ? 'enabled' : 'disabled'}.`);
  await loadCatalog();
}

async function togglePackageAvailability(pkg: any) {
  await request(`/admin/catalog/packages/${pkg.id}`, {
    method: 'PATCH',
    body: { is_available: !pkg.is_available },
  });
  toast.success(`${pkg.internal_sku} availability updated.`);
  await loadCatalog();
}

function startEditPrice(pkg: any) {
  editingPkgId.value = pkg.id;
  editCost.value = pkg.base_cost_minor;
  editSell.value = pkg.selling_price_minor;
}

async function savePrice(pkg: any) {
  try {
    await request(`/admin/catalog/packages/${pkg.id}`, {
      method: 'PATCH',
      body: {
        base_cost_minor: Math.round(editCost.value),
        selling_price_minor: Math.round(editSell.value),
      },
    });
    editingPkgId.value = null;
    toast.success(`Updated pricing for ${pkg.internal_sku} (new version).`);
    await loadCatalog();
  } catch (err: any) {
    toast.error(err?.data?.message || 'Failed to update price.');
  }
}

function calcMarginPct(cost: number, sell: number): string {
  if (sell <= 0) return '0.0';
  return (((sell - cost) / sell) * 100).toFixed(1);
}

onMounted(loadCatalog);
</script>

<template>
  <div class="space-y-6">
    <div>
      <h1 class="text-2xl font-bold text-white">Game & Package Catalog</h1>
      <p class="text-xs text-slate-400">Manage maintenance mode, package availability, and live selling prices with margin preview</p>
    </div>

    <div v-for="game in games" :key="game.id" class="admin-card rounded-3xl p-5 sm:p-6 space-y-5">
      <div class="flex flex-wrap items-center justify-between gap-3 border-b border-white/10 pb-4">
        <div>
          <h2 class="text-lg font-bold text-white">{{ game.name }}</h2>
          <p class="text-xs text-slate-400">{{ game.publisher }} • <span class="font-mono text-sky-400">/{{ game.slug }}</span></p>
        </div>
        <button
          type="button"
          class="inline-flex items-center gap-1.5 rounded-xl px-4 py-2 text-xs font-bold transition-all active:scale-95"
          :class="game.is_in_maintenance
            ? 'bg-amber-400 text-slate-950'
            : 'border border-white/15 bg-white/5 text-slate-300 hover:text-white'"
          @click="toggleMaintenance(game)"
        >
          <Wrench class="h-3.5 w-3.5" />
          <span>{{ game.is_in_maintenance ? 'In Maintenance (Click to Resume)' : 'Set Maintenance' }}</span>
        </button>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3.5">
        <div v-for="pkg in game.packages || []" :key="pkg.id" class="rounded-2xl border border-white/10 bg-[#070B16]/90 p-4 text-xs space-y-2.5 flex flex-col justify-between">
          <div class="space-y-1">
            <div class="flex items-start justify-between gap-2">
              <p class="font-bold text-white">{{ pkg.name_en }}</p>
              <span class="text-[10px] font-mono text-slate-500">v{{ pkg.version }}</span>
            </div>
            <p class="font-mono text-[11px] text-slate-400">{{ pkg.internal_sku }}</p>
          </div>

          <!-- Inline Price & Profit Editor -->
          <div v-if="editingPkgId === pkg.id" class="space-y-2 pt-2 border-t border-white/10">
            <div class="grid grid-cols-2 gap-2">
              <div>
                <label class="block text-[10px] text-slate-400 mb-0.5">Cost (minor)</label>
                <input v-model.number="editCost" type="number" class="w-full rounded-lg border border-white/15 bg-white/5 px-2 py-1 text-xs text-white font-mono" />
              </div>
              <div>
                <label class="block text-[10px] text-slate-400 mb-0.5">Sell (minor)</label>
                <input v-model.number="editSell" type="number" class="w-full rounded-lg border border-sky-400/50 bg-white/5 px-2 py-1 text-xs text-sky-300 font-mono" />
              </div>
            </div>
            <p class="text-[11px] text-emerald-400 font-semibold">
              Profit: {{ formatMoney(editSell - editCost, pkg.currency) }} ({{ calcMarginPct(editCost, editSell) }}%)
            </p>
            <div class="flex gap-1.5">
              <button type="button" class="flex-1 rounded-lg bg-emerald-600 py-1.5 text-[11px] font-bold text-white inline-flex items-center justify-center gap-1" @click="savePrice(pkg)">
                <Check class="h-3 w-3" /> Save
              </button>
              <button type="button" class="rounded-lg border border-white/15 px-2.5 py-1.5 text-[11px] text-slate-300" @click="editingPkgId = null">
                <X class="h-3 w-3" />
              </button>
            </div>
          </div>

          <div v-else class="space-y-2 pt-2 border-t border-white/5">
            <div class="flex items-center justify-between">
              <span class="text-slate-400">Cost: {{ formatMoney(pkg.base_cost_minor, pkg.currency) }}</span>
              <span class="text-sky-300 font-bold tabular-nums">{{ formatMoney(pkg.selling_price_minor, pkg.currency) }}</span>
            </div>
            <p class="text-[11px] text-emerald-400/90 font-semibold">
              Margin: {{ calcMarginPct(pkg.base_cost_minor, pkg.selling_price_minor) }}%
            </p>
            <div class="flex items-center gap-2 pt-1">
              <button
                type="button"
                class="flex-1 rounded-lg px-2.5 py-1.5 text-[11px] font-bold transition-colors"
                :class="pkg.is_available ? 'bg-emerald-500/20 text-emerald-300' : 'bg-red-500/20 text-red-300'"
                @click="togglePackageAvailability(pkg)"
              >
                {{ pkg.is_available ? 'Available' : 'Disabled' }}
              </button>
              <button type="button" class="rounded-lg border border-white/15 bg-white/5 hover:bg-white/10 p-1.5 text-sky-300" title="Edit price" @click="startEditPrice(pkg)">
                <Pencil class="h-3.5 w-3.5" />
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
