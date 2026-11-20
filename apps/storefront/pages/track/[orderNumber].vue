<script setup lang="ts">
import { formatMoney, type OrderSummary } from '@topup/shared-types';
import { CheckCircle2, Clock, AlertTriangle, XCircle, RefreshCw } from 'lucide-vue-next';

const route = useRoute();
const { request } = useApi();
const { locale } = useI18n();

const orderNumber = computed(() => String(route.params.orderNumber));
const token = ref<string>(String(route.query.token || ''));
const order = ref<OrderSummary | null>(null);
const loading = ref(true);
const error = ref<string | null>(null);

const supportMessage = ref('');
const supportSubmitted = ref(false);

async function fetchStatus() {
  loading.value = true;
  error.value = null;
  try {
    const res = await request<{ data: OrderSummary }>(
      `/orders/${orderNumber.value}/track?token=${encodeURIComponent(token.value)}`,
    );
    order.value = res.data;
  } catch (err: any) {
    error.value = err?.data?.message || 'Unable to load order tracking details.';
  } finally {
    loading.value = false;
  }
}

onMounted(() => {
  if (!token.value && import.meta.client) {
    token.value = sessionStorage.getItem(`ktu_track_${orderNumber.value}`) || '';
  }
  fetchStatus();
});

async function submitSupport() {
  if (!supportMessage.value.trim()) return;
  await request('/support/tickets', {
    method: 'POST',
    body: {
      order_number: orderNumber.value,
      tracking_token: token.value,
      subject: `Order Inquiry: ${orderNumber.value}`,
      message: supportMessage.value.trim(),
      contact_email: order.value?.customer_email || null,
    },
  });
  supportSubmitted.value = true;
  supportMessage.value = '';
}
</script>

<template>
  <div class="mx-auto max-w-3xl px-4 sm:px-6 py-10 space-y-6">
    <div class="flex items-center justify-between">
      <h1 class="text-2xl font-bold text-white">{{ $t('track.title') }}</h1>
      <button
        type="button"
        class="inline-flex items-center gap-1.5 rounded-xl border border-brand-border bg-brand-surface px-3.5 py-2 text-xs font-semibold text-brand-accent hover:border-brand-accent"
        @click="fetchStatus"
      >
        <RefreshCw class="h-3.5 w-3.5" :class="{ 'animate-spin': loading }" />
        <span>{{ $t('track.refresh') }}</span>
      </button>
    </div>

    <div v-if="error" class="glass-card rounded-2xl p-6 text-sm text-red-300 border-red-500/40">
      {{ error }}
    </div>

    <div v-else-if="order" class="space-y-6">
      <!-- Rare Delight: Order Completed Celebration Banner -->
      <div
        v-if="order.status === 'completed'"
        class="animate-success-burst rounded-2xl border border-emerald-400/40 bg-gradient-to-r from-emerald-500/20 via-emerald-950/60 to-sky-500/15 p-5 flex items-center gap-4 shadow-lg shadow-emerald-950/40"
      >
        <div class="h-12 w-12 rounded-2xl bg-emerald-500/25 border border-emerald-400/50 flex items-center justify-center shrink-0">
          <CheckCircle2 class="h-7 w-7 text-emerald-300" />
        </div>
        <div>
          <p class="text-sm sm:text-base font-extrabold text-white">
            {{ locale === 'km' ? 'បញ្ចូលទឹកប្រាក់ហ្គេមជោគជ័យ ១០០%!' : 'Top-Up Delivered Successfully!' }}
          </p>
          <p class="text-xs text-emerald-200/90 mt-0.5">
            {{ locale === 'km' ? 'សូមពិនិត្យមើលក្នុងគណនីហ្គេមរបស់អ្នកឥឡូវនេះ។ អរគុណដែលបានប្រើប្រាស់ VannTopUp!' : 'Your game currency has been delivered directly to your account. Thank you for using VannTopUp!' }}
          </p>
        </div>
      </div>

      <!-- Status Banner -->
      <div class="glass-card rounded-2xl p-6 space-y-4">
        <div class="flex flex-wrap items-center justify-between gap-3">
          <div>
            <p class="text-xs text-brand-muted">{{ $t('checkout.orderNumber') }}</p>
            <p class="font-mono text-lg font-bold text-white">{{ order.order_number }}</p>
          </div>
          <span
            class="rounded-full px-3.5 py-1 text-xs font-bold uppercase tracking-wider"
            :class="{
              'bg-emerald-500/20 text-emerald-300': order.status === 'completed',
              'bg-amber-500/20 text-amber-300': order.status === 'needs_review' || order.status === 'awaiting_payment',
              'bg-blue-500/20 text-blue-300': order.status === 'paid' || order.status === 'fulfilling',
              'bg-red-500/20 text-red-300': order.status === 'failed' || order.status === 'expired',
            }"
          >
            {{ order.status.replace('_', ' ') }}
          </span>
        </div>

        <!-- Timeline -->
        <div class="grid grid-cols-1 sm:grid-cols-4 gap-3 pt-4 border-t border-brand-border">
          <div
            v-for="step in order.timeline || []"
            :key="step.code"
            class="rounded-xl border p-3.5 space-y-1.5"
            :class="{
              'border-emerald-500/40 bg-emerald-500/10': step.status === 'completed',
              'border-brand-accent/50 bg-brand-primary/15': step.status === 'active',
              'border-amber-500/40 bg-amber-500/10': step.status === 'warning',
              'border-red-500/40 bg-red-500/10': step.status === 'failed',
              'border-brand-border bg-brand-bg/60': step.status === 'pending',
            }"
          >
            <div class="flex items-center gap-1.5">
              <CheckCircle2 v-if="step.status === 'completed'" class="h-4 w-4 text-emerald-400" />
              <AlertTriangle v-else-if="step.status === 'warning'" class="h-4 w-4 text-amber-400" />
              <XCircle v-else-if="step.status === 'failed'" class="h-4 w-4 text-red-400" />
              <Clock v-else class="h-4 w-4 text-brand-muted" />
              <span class="text-xs font-semibold text-white">
                {{ locale === 'km' ? step.label_km : step.label_en }}
              </span>
            </div>
            <p v-if="step.timestamp" class="text-[10px] text-brand-muted">
              {{ new Date(step.timestamp).toLocaleString() }}
            </p>
          </div>
        </div>

        <!-- Receipt -->
        <div class="grid grid-cols-2 sm:grid-cols-3 gap-4 pt-4 border-t border-brand-border text-xs">
          <div>
            <p class="text-brand-muted">Game</p>
            <p class="font-semibold text-white mt-0.5">{{ order.game_name }}</p>
          </div>
          <div>
            <p class="text-brand-muted">Package</p>
            <p class="font-semibold text-white mt-0.5">{{ locale === 'km' ? order.package_name_km : order.package_name_en }}</p>
          </div>
          <div>
            <p class="text-brand-muted">Total Paid</p>
            <p class="font-bold text-brand-accent mt-0.5">{{ formatMoney(order.total_minor, order.currency) }}</p>
          </div>
          <div>
            <p class="text-brand-muted">Player (Masked)</p>
            <p class="font-mono text-white mt-0.5">{{ order.masked_player_identifier }}</p>
          </div>
          <div>
            <p class="text-brand-muted">Payment Status</p>
            <p class="font-semibold text-white uppercase mt-0.5">{{ order.payment_status }}</p>
          </div>
          <div>
            <p class="text-brand-muted">Fulfillment Status</p>
            <p class="font-semibold text-white uppercase mt-0.5">{{ order.fulfillment_status }}</p>
          </div>
        </div>
      </div>

      <!-- Support Box -->
      <div class="glass-card rounded-2xl p-6 space-y-3">
        <h2 class="text-sm font-bold text-white">{{ $t('track.needHelp') }}</h2>
        <div v-if="supportSubmitted" class="text-xs text-emerald-400">
          Support ticket created. Our Cambodia support team will review your order shortly.
        </div>
        <form v-else class="space-y-3" @submit.prevent="submitSupport">
          <textarea
            v-model="supportMessage"
            rows="3"
            minlength="10"
            required
            placeholder="Describe any issue with your order..."
            class="w-full rounded-xl border border-brand-border bg-brand-bg p-3 text-xs text-white focus:border-brand-accent focus:outline-none"
          />
          <button type="submit" class="rounded-xl bg-brand-primary px-4 py-2 text-xs font-semibold text-white hover:bg-brand-primary-hover">
            Submit Support Request
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
