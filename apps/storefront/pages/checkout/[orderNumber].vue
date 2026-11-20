<script setup lang="ts">
import { formatMoney, type OrderSummary, type PaymentSession } from '@topup/shared-types';
import { ShieldAlert, CheckCircle2, Clock, Copy, Check, Lock } from 'lucide-vue-next';

const route = useRoute();
const router = useRouter();
const { request } = useApi();
const toast = useToast();

const orderNumber = computed(() => String(route.params.orderNumber));
const trackingToken = ref<string>(String(route.query.token || ''));
const order = ref<OrderSummary | null>(null);
const session = ref<PaymentSession | null>(null);
const loading = ref(true);
const simulating = ref(false);
const error = ref<string | null>(null);
const copied = ref(false);
const remainingSeconds = ref(900);

let timerHandle: ReturnType<typeof setInterval> | null = null;

const formattedCountdown = computed(() => {
  const s = Math.max(0, remainingSeconds.value);
  const mins = Math.floor(s / 60).toString().padStart(2, '0');
  const secs = (s % 60).toString().padStart(2, '0');
  return `${mins}:${secs}`;
});

onMounted(async () => {
  if (!trackingToken.value && import.meta.client) {
    trackingToken.value = sessionStorage.getItem(`ktu_track_${orderNumber.value}`) || '';
  }

  try {
    const orderRes = await request<{ data: OrderSummary }>(
      `/orders/${orderNumber.value}/track?token=${encodeURIComponent(trackingToken.value)}`,
    );
    order.value = orderRes.data;

    if (order.value.payment_status === 'paid') {
      await router.replace({ path: `/track/${orderNumber.value}`, query: { token: trackingToken.value } });
      return;
    }

    const payRes = await request<{ data: PaymentSession }>(
      `/orders/${orderNumber.value}/payment-sessions`,
      {
        method: 'POST',
        body: { provider: 'mock_payway', tracking_token: trackingToken.value },
      },
    );
    session.value = payRes.data;

    const diff = Math.floor((new Date(payRes.data.expires_at).getTime() - Date.now()) / 1000);
    remainingSeconds.value = Math.max(0, diff);
    timerHandle = setInterval(() => {
      if (remainingSeconds.value > 0) remainingSeconds.value -= 1;
    }, 1000);
  } catch (err: any) {
    error.value = err?.data?.message || 'Unable to initialize checkout session.';
  } finally {
    loading.value = false;
  }
});

onUnmounted(() => {
  if (timerHandle) clearInterval(timerHandle);
});

async function simulateOutcome(outcome: 'paid' | 'failed' | 'expired') {
  if (!session.value) return;
  simulating.value = true;
  error.value = null;
  try {
    await request(`/payments/${session.value.payment_intent_id}/simulate`, {
      method: 'POST',
      body: { outcome, tracking_token: trackingToken.value },
    });
    toast.success(`Payment webhook simulated: ${outcome.toUpperCase()}`);
    await router.push({
      path: `/track/${orderNumber.value}`,
      query: { token: trackingToken.value },
    });
  } catch (err: any) {
    error.value = err?.data?.message || 'Payment simulation failed.';
    toast.error(error.value!);
  } finally {
    simulating.value = false;
  }
}

async function copyToken() {
  if (!trackingToken.value || !import.meta.client) return;
  await navigator.clipboard.writeText(trackingToken.value);
  copied.value = true;
  toast.success('Tracking token copied!');
  setTimeout(() => { copied.value = false; }, 2000);
}
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 sm:px-6 py-10 space-y-6">
    <div class="flex flex-wrap items-center justify-between gap-3">
      <div>
        <p class="text-xs font-bold uppercase tracking-widest text-sky-400">Secure Checkout</p>
        <h1 class="text-2xl sm:text-3xl font-extrabold text-white mt-0.5">{{ $t('checkout.title') }}</h1>
      </div>
      <div v-if="session" class="inline-flex items-center gap-2 rounded-xl border border-amber-400/30 bg-amber-500/10 px-4 py-2 text-xs font-bold text-amber-300">
        <Clock class="h-4 w-4" />
        <span class="font-mono text-sm">{{ formattedCountdown }}</span>
      </div>
    </div>

    <div v-if="loading" class="glass-card rounded-3xl p-10 text-center text-slate-400">
      Initializing verified KHQR payment session...
    </div>

    <div v-else-if="error" class="glass-card rounded-3xl p-6 border-red-500/40 text-red-300 text-sm">
      {{ error }}
    </div>

    <div v-else-if="order && session" class="grid grid-cols-1 md:grid-cols-12 gap-6 items-start">
      <!-- Authentic KHQR Card -->
      <div class="md:col-span-6 glass-card rounded-3xl p-6 space-y-5">
        <div
          v-if="session.provider_mode === 'mock'"
          class="rounded-xl bg-amber-500/15 border border-amber-500/40 px-3.5 py-2.5 text-[11px] font-bold text-amber-300 flex items-center gap-2"
        >
          <ShieldAlert class="h-4 w-4 shrink-0" />
          <span>{{ $t('checkout.devWarning') }}</span>
        </div>

        <!-- Iconic KHQR Frame -->
        <div class="mx-auto max-w-[270px] rounded-2xl overflow-hidden bg-white text-slate-900 shadow-2xl border border-white/20">
          <div class="bg-[#E1232E] px-4 py-2.5 flex items-center justify-between text-white">
            <span class="font-black tracking-wider text-sm">KHQR</span>
            <span class="text-[10px] font-semibold opacity-90">BAKONG • ABA</span>
          </div>
          <div class="px-5 pt-4 pb-2 border-b border-dashed border-slate-200 text-left">
            <p class="text-[11px] font-semibold text-slate-500 uppercase">VannTopUp Cambodia</p>
            <p class="text-2xl font-black text-slate-900 mt-0.5">{{ formatMoney(order.total_minor, order.currency) }}</p>
          </div>
          <div class="p-5 flex flex-col items-center">
            <!-- Crisp Deterministic SVG QR Matrix -->
            <svg viewBox="0 0 120 120" class="w-44 h-44 text-slate-900" fill="currentColor" aria-label="KHQR Code">
              <rect x="6" y="6" width="28" height="28" rx="4" fill="none" stroke="currentColor" stroke-width="6" />
              <rect x="14" y="14" width="12" height="12" rx="1.5" />
              <rect x="86" y="6" width="28" height="28" rx="4" fill="none" stroke="currentColor" stroke-width="6" />
              <rect x="94" y="14" width="12" height="12" rx="1.5" />
              <rect x="6" y="86" width="28" height="28" rx="4" fill="none" stroke="currentColor" stroke-width="6" />
              <rect x="14" y="94" width="12" height="12" rx="1.5" />
              <rect x="44" y="10" width="8" height="8" /><rect x="60" y="10" width="8" height="8" /><rect x="70" y="18" width="8" height="8" />
              <rect x="44" y="26" width="8" height="8" /><rect x="54" y="26" width="14" height="8" /><rect x="44" y="44" width="8" height="8" />
              <rect x="10" y="46" width="8" height="8" /><rect x="24" y="46" width="12" height="8" /><rect x="10" y="62" width="16" height="8" />
              <rect x="34" y="62" width="8" height="14" /><rect x="78" y="46" width="12" height="8" /><rect x="98" y="46" width="12" height="8" />
              <rect x="78" y="62" width="8" height="14" /><rect x="94" y="62" width="16" height="8" /><rect x="46" y="84" width="14" height="8" />
              <rect x="68" y="84" width="10" height="8" /><rect x="86" y="84" width="14" height="8" /><rect x="104" y="94" width="10" height="16" />
              <rect x="46" y="100" width="10" height="10" /><rect x="64" y="98" width="16" height="12" /><rect x="88" y="102" width="10" height="8" />
              <circle cx="60" cy="60" r="11" fill="#E1232E" />
              <text x="60" y="64" text-anchor="middle" fill="#FFFFFF" font-size="11" font-weight="900">$</text>
            </svg>
            <p class="mt-2 font-mono text-[10px] text-slate-400">{{ session.reference }}</p>
          </div>
        </div>

        <p class="text-xs text-slate-400 text-center leading-relaxed">
          {{ $i18n.locale === 'km' ? session.instructions_km : session.instructions_en }}
        </p>

        <!-- Dev Simulator Buttons -->
        <div v-if="session.provider_mode === 'mock'" class="pt-4 border-t border-white/10 space-y-2.5">
          <button
            type="button"
            :disabled="simulating"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 py-3 text-xs font-extrabold text-white shadow-lg shadow-emerald-900/30 transition-all active:scale-[0.98]"
            @click="simulateOutcome('paid')"
          >
            <CheckCircle2 class="h-4 w-4" />
            <span>{{ $t('checkout.simulatePaid') }}</span>
          </button>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              :disabled="simulating"
              class="rounded-xl bg-red-600/80 hover:bg-red-600 disabled:opacity-50 py-2.5 text-xs font-bold text-white"
              @click="simulateOutcome('failed')"
            >
              {{ $t('checkout.simulateFailed') }}
            </button>
            <button
              type="button"
              :disabled="simulating"
              class="rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 disabled:opacity-50 py-2.5 text-xs font-bold text-slate-300"
              @click="simulateOutcome('expired')"
            >
              {{ $t('checkout.simulateExpired') }}
            </button>
          </div>
        </div>
      </div>

      <!-- Order Summary & Secret Tracking Token -->
      <div class="md:col-span-6 space-y-5">
        <div class="glass-card rounded-3xl p-6 space-y-4">
          <div class="flex items-center justify-between border-b border-white/10 pb-4">
            <div>
              <p class="text-[11px] text-slate-400">{{ $t('checkout.orderNumber') }}</p>
              <p class="font-mono text-base font-extrabold text-white">{{ order.order_number }}</p>
            </div>
            <span class="inline-flex items-center gap-1 rounded-full bg-sky-500/15 border border-sky-400/30 px-3 py-1 text-[11px] font-bold text-sky-300">
              <Lock class="h-3 w-3" /> Server-Verified
            </span>
          </div>

          <div class="grid grid-cols-2 gap-4 text-xs">
            <div>
              <p class="text-slate-400">Game</p>
              <p class="font-bold text-white mt-0.5">{{ order.game_name }}</p>
            </div>
            <div>
              <p class="text-slate-400">Package</p>
              <p class="font-bold text-white mt-0.5">{{ order.package_name_en }}</p>
            </div>
            <div class="col-span-2">
              <p class="text-slate-400">Player (Masked)</p>
              <p class="font-mono font-semibold text-sky-300 mt-0.5">{{ order.masked_player_identifier }}</p>
            </div>
          </div>
        </div>

        <div v-if="trackingToken" class="glass-card rounded-3xl p-6 space-y-2.5 border-sky-400/25">
          <p class="text-xs font-semibold text-slate-300">{{ $t('checkout.saveTokenNotice') }}</p>
          <div class="flex items-center gap-2 rounded-xl bg-[#070B16] border border-white/10 p-3">
            <code class="flex-1 text-[11px] font-mono text-sky-300 break-all">{{ trackingToken }}</code>
            <button
              type="button"
              class="shrink-0 inline-flex items-center gap-1 rounded-lg bg-white/10 hover:bg-white/20 px-3 py-1.5 text-xs font-bold text-white"
              @click="copyToken"
            >
              <Check v-if="copied" class="h-3.5 w-3.5 text-emerald-400" />
              <Copy v-else class="h-3.5 w-3.5" />
              <span>{{ copied ? 'Copied' : 'Copy' }}</span>
            </button>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
