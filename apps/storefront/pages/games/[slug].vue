<script setup lang="ts">
import { formatMoney, type Game, type GamePackage, type PlayerValidationResult, type PriceQuote, type OrderSummary } from '@topup/shared-types';
import {
  CheckCircle2, AlertCircle, ShieldCheck, Tag, Loader2, Gem, QrCode,
  Sparkles, ArrowLeft, Mail, Phone, Hash, Check, ArrowRight
} from 'lucide-vue-next';
import { usePreferencesStore } from '~/stores/preferences';

const route = useRoute();
const router = useRouter();
const { request } = useApi();
const { locale } = useI18n();
const prefs = usePreferencesStore();
const toast = useToast();

const slug = computed(() => String(route.params.slug));

const artworkMap: Record<string, string> = {
  'mobile-legends': '/images/games/mobile-legends.jpg',
  'free-fire': '/images/games/free-fire.jpg',
  'pubg-mobile': '/images/games/pubg-mobile.jpg',
  'honor-of-kings': '/images/games/honor-of-kings.jpg',
};

const logoMap: Record<string, string> = {
  'mobile-legends': '/images/games/icons/mobile-legends-logo.jpg',
  'free-fire': '/images/games/icons/free-fire-logo.jpg',
  'pubg-mobile': '/images/games/icons/pubg-mobile-logo.jpg',
  'honor-of-kings': '/images/games/icons/honor-of-kings-logo.jpg',
};

const { data: game, error: loadError } = await useAsyncData(`game-${slug.value}`, async () => {
  const res = await request<{ data: Game }>(`/games/${slug.value}`);
  return res.data;
});

const coverImage = computed(() => artworkMap[slug.value] || game.value?.thumbnail_url || '/images/games/mlbb-thumb.svg');
const gameLogo = computed(() => logoMap[slug.value] || coverImage.value);

const selectedRegion = ref<string>(game.value?.regions?.[0]?.code || 'KH');
const selectedPackage = ref<GamePackage | null>(game.value?.packages?.[0] || null);
const selectedPaymentMethod = ref<'mock_khqr' | 'aba_khqr'>('mock_khqr');
const playerFields = reactive<Record<string, string>>({});
const couponCode = ref('');
const customerEmail = ref('');
const customerPhone = ref('');

const validationResult = ref<PlayerValidationResult | null>(null);
const validatingPlayer = ref(false);
const showIdGuide = ref(false);
const quote = ref<PriceQuote | null>(null);
const loadingQuote = ref(false);
const quoteError = ref<string | null>(null);
const submittingOrder = ref(false);
const orderError = ref<string | null>(null);

async function fetchQuote(showToast = false) {
  if (!game.value || !selectedPackage.value) return;
  loadingQuote.value = true;
  quoteError.value = null;
  try {
    const res = await request<{ data: PriceQuote }>('/quotes', {
      method: 'POST',
      body: {
        game_id: game.value.id,
        package_id: selectedPackage.value.id,
        region_code: selectedRegion.value,
        currency: prefs.currency,
        payment_method: selectedPaymentMethod.value,
        coupon_code: couponCode.value.trim() || null,
      },
    });
    quote.value = res.data;
    if (showToast && couponCode.value.trim()) {
      toast.success(locale.value === 'km' ? 'បានអនុវត្តកូដបញ្ចុះតម្លៃជោគជ័យ!' : 'Promo code applied!');
    }
  } catch (err: any) {
    quoteError.value = err?.data?.message || 'Unable to compute price quote.';
    quote.value = null;
    if (showToast) toast.error(quoteError.value!);
  } finally {
    loadingQuote.value = false;
  }
}

watch([selectedPackage, selectedRegion, selectedPaymentMethod, () => prefs.currency], () => {
  fetchQuote(false);
}, { immediate: true });

function applyQuickCoupon(code: string) {
  couponCode.value = code;
  fetchQuote(true);
}

async function handleVerifyPlayer() {
  if (!game.value) return;
  validatingPlayer.value = true;
  validationResult.value = null;
  try {
    validationResult.value = await request<PlayerValidationResult>(`/games/${game.value.slug}/validate-player`, {
      method: 'POST',
      body: {
        region_code: selectedRegion.value,
        player_fields: { ...playerFields },
      },
    });
    if (validationResult.value.valid) {
      toast.success(`${validationResult.value.player_name || 'Player'} verified!`);
    }
  } catch (err: any) {
    validationResult.value = {
      valid: false,
      supported: true,
      message: err?.data?.message || 'Player verification failed.',
    };
    toast.error(validationResult.value.message);
  } finally {
    validatingPlayer.value = false;
  }
}

async function handleCheckout() {
  if (!quote.value) return;
  submittingOrder.value = true;
  orderError.value = null;
  try {
    const idempotencyKey = `web-${Date.now()}-${Math.random().toString(36).slice(2, 10)}`;
    const res = await request<{ data: OrderSummary }>('/orders', {
      method: 'POST',
      body: {
        quote_id: quote.value.id,
        quote_signature: quote.value.signature,
        player_fields: { ...playerFields },
        customer_email: customerEmail.value.trim() || null,
        customer_phone: customerPhone.value.trim() || null,
        idempotency_key: idempotencyKey,
        locale: locale.value,
      },
    });

    const order = res.data;
    if (import.meta.client && order.tracking_token) {
      sessionStorage.setItem(`ktu_track_${order.order_number}`, order.tracking_token);
    }

    await router.push({
      path: `/checkout/${order.order_number}`,
      query: order.tracking_token ? { token: order.tracking_token } : {},
    });
  } catch (err: any) {
    orderError.value = err?.data?.message || 'Could not create order. Please check your Player ID fields.';
    toast.error(orderError.value!);
  } finally {
    submittingOrder.value = false;
  }
}
</script>

<template>
  <div class="pb-28 lg:pb-12">
    <div v-if="loadError || !game" class="mx-auto max-w-3xl px-4 py-16">
      <div class="glass-card rounded-2xl p-8 text-center">
        <p class="text-red-400 font-bold">Game not found or currently unavailable.</p>
        <NuxtLink to="/games" class="inline-block mt-4 text-sm text-sky-400 underline">Back to Games</NuxtLink>
      </div>
    </div>

    <div v-else>
      <!-- Ambient Game Hero Header -->
      <div class="relative overflow-hidden border-b border-white/10">
        <img :src="coverImage" :alt="game.name" class="absolute inset-0 w-full h-full object-cover opacity-25 blur-xl scale-110 pointer-events-none" />
        <div class="absolute inset-0 bg-gradient-to-b from-[#070B16]/70 via-[#070B16]/85 to-[#070B16]" />

        <div class="relative mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-6 sm:py-9">
          <NuxtLink to="/games" class="inline-flex items-center gap-1.5 text-xs font-semibold text-slate-300 hover:text-sky-400 mb-4">
            <ArrowLeft class="h-3.5 w-3.5" /> {{ $t('nav.games') }}
          </NuxtLink>

          <div class="flex items-center gap-4 sm:gap-6">
            <img
              :src="gameLogo"
              :alt="`${game.name} Official Logo`"
              class="w-16 h-16 sm:w-24 sm:h-24 rounded-2xl sm:rounded-3xl object-cover border-2 border-white/25 shadow-2xl shrink-0"
            />
            <div class="space-y-1.5 min-w-0">
              <div class="flex flex-wrap items-center gap-1.5 sm:gap-2">
                <span class="rounded-md bg-sky-500/20 border border-sky-400/30 px-2 py-0.5 text-[10px] sm:text-[11px] font-bold uppercase tracking-wider text-sky-300">
                  {{ game.publisher }}
                </span>
                <span class="rounded-md bg-emerald-500/15 border border-emerald-400/30 px-2 py-0.5 text-[10px] sm:text-[11px] font-bold text-emerald-300">
                  ⚡ 3s Instant
                </span>
              </div>
              <h1 class="text-xl sm:text-3xl font-bold text-white tracking-tight truncate">{{ game.name }}</h1>
              <p class="hidden sm:block text-sm text-slate-300 max-w-2xl leading-relaxed">
                {{ locale === 'km' ? game.description_km : game.description_en }}
              </p>
            </div>
          </div>
        </div>
      </div>

      <!-- Main Numbered Checkout Builder -->
      <div class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 pt-6 sm:pt-8 grid grid-cols-1 lg:grid-cols-12 gap-5 sm:gap-8">
        <div class="lg:col-span-8 space-y-5 sm:space-y-6">
          <!-- STEP 1: Player Details -->
          <div class="glass-card rounded-2xl sm:rounded-3xl p-4 sm:p-7 space-y-4 sm:space-y-5">
            <div class="flex items-center justify-between gap-2">
              <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-sky-400 text-xs font-bold text-white shadow-lg">01</span>
                <h2 class="text-base sm:text-lg font-bold text-white leading-normal">{{ $t('game.playerDetails') }}</h2>
              </div>
              <button
                type="button"
                class="text-[11px] font-bold text-sky-400 hover:text-sky-300 underline underline-offset-2"
                @click="showIdGuide = !showIdGuide"
              >
                {{ locale === 'km' ? 'របៀបរក ID?' : 'How to find ID?' }}
              </button>
            </div>

            <Transition name="drawer">
              <div v-if="showIdGuide" class="rounded-xl border border-sky-400/30 bg-sky-500/10 p-3.5 text-xs text-slate-200 leading-relaxed">
                {{ locale === 'km'
                  ? 'បើកហ្គេម → ចុចលើរូប Profile របស់អ្នកនៅជ្រុងខាងលើឆ្វេង → ចម្លងលេខ User ID (និងលេខក្នុងវង់ក្រចក Zone ID សម្រាប់ Mobile Legends ឧ. 123456789 (2014))។'
                  : 'Open the game → Tap your Profile avatar in the top-left corner → Copy your numeric Player/User ID (and the 4–5 digit Zone ID in parentheses for MLBB, e.g. 123456789 (2014)).' }}
              </div>
            </Transition>

            <div v-if="game.regions.length > 0">
              <label class="block text-xs font-medium text-slate-400 mb-2">{{ $t('game.selectRegion') }}</label>
              <div class="flex flex-wrap gap-2">
                <button
                  v-for="region in game.regions"
                  :key="region.code"
                  type="button"
                  class="px-3.5 py-2 rounded-xl text-xs font-semibold border transition-all active:scale-95"
                  :class="selectedRegion === region.code
                    ? 'bg-blue-600/25 border-sky-400 text-white shadow-md shadow-blue-900/30'
                    : 'bg-[#090F1E] border-white/10 text-slate-400 hover:text-white hover:border-white/25'"
                  @click="selectedRegion = region.code"
                >
                  {{ locale === 'km' ? region.name_km : region.name_en }}
                </button>
              </div>
            </div>

            <!-- Compact 2-column player inputs on mobile when 2 fields exist -->
            <div class="grid gap-3 sm:gap-4" :class="game.fields.length === 2 ? 'grid-cols-12' : 'grid-cols-1'">
              <div
                v-for="(field, fIdx) in game.fields"
                :key="field.key"
                :class="game.fields.length === 2 ? (fIdx === 0 ? 'col-span-7' : 'col-span-5') : 'col-span-1'"
              >
                <label :for="`field-${field.key}`" class="block text-xs font-medium text-slate-300 mb-1.5 truncate">
                  {{ locale === 'km' ? field.label_km : field.label_en }}
                  <span v-if="field.is_required" class="text-red-400">*</span>
                </label>
                <div class="relative">
                  <Hash class="h-3.5 w-3.5 text-slate-500 absolute left-3 top-1/2 -translate-y-1/2" />
                  <input
                    :id="`field-${field.key}`"
                    v-model="playerFields[field.key]"
                    :type="field.field_type === 'number' ? 'text' : field.field_type"
                    :inputmode="field.field_type === 'number' ? 'numeric' : 'text'"
                    :placeholder="(locale === 'km' ? field.placeholder_km : field.placeholder_en) || ''"
                    class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-8 pr-3 py-2.5 sm:py-3 text-sm font-medium text-white placeholder-slate-500 focus:border-sky-400 focus:outline-none transition-colors"
                  />
                </div>
              </div>
            </div>

            <div class="flex flex-wrap items-center gap-2.5">
              <button
                v-if="game.supports_player_validation"
                type="button"
                :disabled="validatingPlayer"
                class="inline-flex items-center gap-2 rounded-xl border border-sky-400/50 bg-sky-500/15 px-4 py-2.5 text-xs font-semibold text-sky-300 hover:bg-sky-500/25 disabled:opacity-50 active:scale-95 transition-all"
                @click="handleVerifyPlayer"
              >
                <Loader2 v-if="validatingPlayer" class="h-3.5 w-3.5 animate-spin" />
                <ShieldCheck v-else class="h-4 w-4" />
                <span>{{ validatingPlayer ? $t('game.verifying') : $t('game.verifyPlayer') }}</span>
              </button>

              <Transition name="pop" mode="out-in">
                <div
                  v-if="validationResult"
                  :key="validationResult.message"
                  class="inline-flex items-center gap-2 text-xs font-semibold px-3.5 py-2 rounded-xl border"
                  :class="validationResult.valid
                    ? 'bg-emerald-500/15 border-emerald-500/30 text-emerald-300'
                    : 'bg-red-500/15 border-red-500/30 text-red-300'"
                >
                  <CheckCircle2 v-if="validationResult.valid" class="h-4 w-4 shrink-0" />
                  <AlertCircle v-else class="h-4 w-4 shrink-0" />
                  <span>{{ validationResult.player_name ? `${validationResult.player_name} — ` : '' }}{{ validationResult.message }}</span>
                </div>
              </Transition>
            </div>
          </div>

          <!-- STEP 2: Compact 2-Column Mobile / 2-Column Desktop Package Grid -->
          <div class="glass-card rounded-2xl sm:rounded-3xl p-4 sm:p-7 space-y-4 sm:space-y-5">
            <div class="flex items-center justify-between">
              <div class="flex items-center gap-3">
                <span class="flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-sky-400 text-xs font-bold text-white shadow-lg">02</span>
                <h2 class="text-base sm:text-lg font-bold text-white leading-normal">{{ $t('game.selectPackage') }}</h2>
              </div>
              <span class="text-[11px] text-slate-400">{{ (game.packages || []).length }} {{ locale === 'km' ? 'កញ្ចប់' : 'items' }}</span>
            </div>

            <div class="grid grid-cols-2 gap-2.5 sm:gap-3.5">
              <button
                v-for="pkg in game.packages || []"
                :key="pkg.id"
                type="button"
                class="relative text-left rounded-2xl border p-3 sm:p-4 transition-all active:scale-[0.98] flex flex-col justify-between gap-2.5"
                :class="selectedPackage?.id === pkg.id
                  ? 'border-sky-400 bg-gradient-to-br from-blue-600/25 to-sky-500/10 ring-2 ring-sky-400/35 shadow-lg shadow-blue-900/35'
                  : 'border-white/10 bg-[#080E1C]/90 hover:border-sky-400/40 hover:bg-[#0D162C]'"
                @click="selectedPackage = pkg"
              >
                <!-- Selected Check Badge -->
                <span
                  v-if="selectedPackage?.id === pkg.id"
                  class="absolute -top-1.5 -right-1.5 h-5 w-5 rounded-full bg-sky-400 text-slate-950 flex items-center justify-center shadow"
                >
                  <Check class="h-3 w-3 stroke-[3]" />
                </span>

                <div class="space-y-2">
                  <div class="flex items-center justify-between gap-1.5">
                    <span class="h-7 w-7 sm:h-8 sm:w-8 rounded-lg bg-sky-500/15 border border-sky-400/30 flex items-center justify-center shrink-0">
                      <Gem class="h-3.5 w-3.5 sm:h-4 sm:w-4 text-sky-400" />
                    </span>
                    <span
                      v-if="pkg.bonus_label"
                      class="rounded-full bg-amber-400/20 border border-amber-400/40 px-2 py-0.5 text-[9px] sm:text-[10px] font-bold text-amber-300 truncate"
                    >
                      {{ pkg.bonus_label }}
                    </span>
                  </div>

                  <div>
                    <p class="font-bold text-xs sm:text-sm text-white leading-snug line-clamp-2">
                      {{ locale === 'km' ? pkg.name_km : pkg.name_en }}
                    </p>
                    <p class="hidden sm:block text-[11px] text-slate-400 mt-0.5 line-clamp-1">
                      {{ locale === 'km' ? pkg.entitlement_description_km : pkg.entitlement_description_en }}
                    </p>
                  </div>
                </div>

                <div class="pt-2 border-t border-white/5 flex flex-col sm:flex-row sm:items-baseline sm:justify-between gap-0.5">
                  <span class="text-sm sm:text-base font-bold text-sky-300 tabular-nums">
                    {{ formatMoney(prefs.currency === 'KHR' ? pkg.display_price_khr_minor : pkg.display_price_usd_minor, prefs.currency) }}
                  </span>
                  <span class="text-[10px] sm:text-[11px] text-slate-400 tabular-nums">
                    ≈ {{ formatMoney(prefs.currency === 'KHR' ? pkg.display_price_usd_minor : pkg.display_price_khr_minor, prefs.currency === 'KHR' ? 'USD' : 'KHR') }}
                  </span>
                </div>
              </button>
            </div>
          </div>

          <!-- STEP 3: Payment Method -->
          <div class="glass-card rounded-2xl sm:rounded-3xl p-4 sm:p-7 space-y-4">
            <div class="flex items-center gap-3">
              <span class="flex h-7 w-7 sm:h-8 sm:w-8 items-center justify-center rounded-xl bg-gradient-to-br from-blue-600 to-sky-400 text-xs font-bold text-white shadow-lg">03</span>
              <h2 class="text-base sm:text-lg font-bold text-white leading-normal">{{ locale === 'km' ? 'វិធីសាស្រ្តទូទាត់ប្រាក់' : 'Select Payment Method' }}</h2>
            </div>

            <button
              type="button"
              class="w-full sm:w-auto sm:min-w-[280px] rounded-2xl border p-3.5 sm:p-4 text-left flex items-center justify-between gap-3 transition-all"
              :class="selectedPaymentMethod === 'mock_khqr'
                ? 'border-sky-400 bg-blue-600/20 ring-1 ring-sky-400/40'
                : 'border-white/10 bg-[#080E1C]'"
              @click="selectedPaymentMethod = 'mock_khqr'"
            >
              <div class="flex items-center gap-3">
                <span class="h-10 w-10 rounded-xl bg-red-600/20 border border-red-500/40 flex items-center justify-center shrink-0">
                  <QrCode class="h-5 w-5 text-red-400" />
                </span>
                <div>
                  <p class="text-sm font-bold text-white">Bakong KHQR / ABA PayWay</p>
                  <p class="text-[11px] text-emerald-400 font-semibold">0% Fee • ABA • ACLEDA • Wing</p>
                </div>
              </div>
              <span class="rounded-md bg-amber-500/20 px-2 py-0.5 text-[10px] font-bold text-amber-300 shrink-0">DEV SIM</span>
            </button>
          </div>
        </div>

        <!-- Right Sticky Server-Verified Quote Sidebar -->
        <div class="lg:col-span-4">
          <div class="glass-card rounded-2xl sm:rounded-3xl p-5 sm:p-6 space-y-4 sm:space-y-5 lg:sticky lg:top-24 border-sky-400/25">
            <div class="flex items-center justify-between">
              <h2 class="text-base font-bold text-white flex items-center gap-2 leading-normal">
                <Sparkles class="h-4 w-4 text-sky-400" />
                <span>{{ $t('game.orderSummary') }}</span>
              </h2>
            </div>

            <!-- Selected Package Mini Preview -->
            <div v-if="selectedPackage" class="rounded-xl border border-white/10 bg-[#070B16]/90 p-3 flex items-center gap-3">
              <img :src="gameLogo" :alt="game.name" class="h-10 w-10 rounded-xl object-cover border border-white/15 shrink-0" />
              <div class="min-w-0 flex-1">
                <p class="text-xs font-bold text-white truncate">{{ locale === 'km' ? selectedPackage.name_km : selectedPackage.name_en }}</p>
                <p class="text-[11px] text-slate-400 truncate">{{ game.name }} • {{ selectedRegion }}</p>
              </div>
            </div>

            <!-- Promo Code with 1-Click KHMER10 Chip -->
            <div class="space-y-2">
              <div class="flex items-center justify-between">
                <label class="block text-xs font-medium text-slate-400">{{ $t('game.promoCode') }}</label>
                <button
                  type="button"
                  class="text-[10px] font-bold text-amber-300 bg-amber-400/15 border border-amber-400/30 rounded-md px-2 py-0.5 hover:bg-amber-400/25 transition-colors"
                  @click="applyQuickCoupon('KHMER10')"
                >
                  + KHMER10 (-10%)
                </button>
              </div>
              <div class="flex gap-2">
                <div class="relative flex-1">
                  <Tag class="h-3.5 w-3.5 text-slate-400 absolute left-3 top-1/2 -translate-y-1/2" />
                  <input
                    v-model="couponCode"
                    type="text"
                    placeholder="KHMER10"
                    class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-9 pr-3 py-2.5 text-xs font-mono text-white uppercase placeholder-slate-500 focus:border-sky-400 focus:outline-none"
                  />
                </div>
                <button
                  type="button"
                  class="rounded-xl border border-sky-400/40 bg-sky-500/15 px-3.5 py-2 text-xs font-semibold text-sky-300 hover:bg-sky-500/25 active:scale-95 transition-all shrink-0"
                  @click="fetchQuote(true)"
                >
                  {{ $t('game.applyPromo') }}
                </button>
              </div>
            </div>

            <!-- Optional Contact with Icons -->
            <div class="space-y-2.5">
              <label class="block text-xs font-medium text-slate-400">{{ $t('game.contactOptional') }}</label>
              <div class="relative">
                <Mail class="h-3.5 w-3.5 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  v-model="customerEmail"
                  type="email"
                  placeholder="you@example.com"
                  class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-9 pr-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-sky-400 focus:outline-none"
                />
              </div>
              <div class="relative">
                <Phone class="h-3.5 w-3.5 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input
                  v-model="customerPhone"
                  type="tel"
                  placeholder="+855 12 345 678"
                  class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-9 pr-3.5 py-2.5 text-xs text-white placeholder-slate-500 focus:border-sky-400 focus:outline-none"
                />
              </div>
            </div>

            <!-- Price Breakdown -->
            <div v-if="loadingQuote" class="py-4 text-center text-xs text-slate-400">Calculating verified quote...</div>
            <div v-else-if="quoteError" class="rounded-xl bg-red-500/10 border border-red-500/30 p-3 text-xs text-red-300">
              {{ quoteError }}
            </div>
            <div v-else-if="quote" class="space-y-2 border-t border-white/10 pt-4 text-sm">
              <div class="flex justify-between text-slate-400 text-xs">
                <span>{{ $t('game.subtotal') }}</span>
                <span class="font-semibold text-slate-200 tabular-nums">{{ formatMoney(quote.subtotal_minor, quote.currency) }}</span>
              </div>
              <div v-if="quote.discount_minor > 0" class="flex justify-between text-emerald-400 text-xs font-semibold">
                <span>{{ $t('game.discount') }} ({{ quote.coupon_code }})</span>
                <span class="tabular-nums">-{{ formatMoney(quote.discount_minor, quote.currency) }}</span>
              </div>
              <div class="flex justify-between text-slate-400 text-xs">
                <span>{{ $t('game.fee') }}</span>
                <span class="font-semibold text-slate-200 tabular-nums">{{ formatMoney(quote.payment_fee_minor, quote.currency) }}</span>
              </div>
              <div class="flex justify-between items-baseline border-t border-white/10 pt-3">
                <span class="text-sm font-bold text-white">{{ $t('game.total') }}</span>
                <Transition name="price-pop" mode="out-in">
                  <span :key="`${quote.total_minor}-${quote.currency}`" class="text-2xl font-bold text-sky-300 tabular-nums">
                    {{ formatMoney(quote.total_minor, quote.currency) }}
                  </span>
                </Transition>
              </div>
            </div>

            <div v-if="orderError" class="rounded-xl bg-red-500/10 border border-red-500/30 p-3 text-xs text-red-300">
              {{ orderError }}
            </div>

            <button
              type="button"
              :disabled="!quote || submittingOrder || game.is_in_maintenance"
              class="w-full btn-primary-glow disabled:opacity-50 rounded-2xl py-3.5 text-sm font-bold text-white inline-flex items-center justify-center gap-2"
              @click="handleCheckout"
            >
              <span>{{ submittingOrder ? $t('game.creatingOrder') : $t('game.checkoutNow') }}</span>
              <ArrowRight v-if="!submittingOrder" class="h-4 w-4" />
            </button>
          </div>
        </div>
      </div>

      <!-- Mobile Sticky Floating Checkout Bar (Cleanly styled, zero overlap) -->
      <div v-if="quote" class="lg:hidden fixed bottom-3 inset-x-3 z-30 pb-[env(safe-area-inset-bottom)]">
        <div class="rounded-2xl border border-sky-400/30 bg-[#0A1124]/95 backdrop-blur-2xl shadow-[0_14px_38px_rgba(0,0,0,0.8)] px-4 py-3 flex items-center justify-between gap-3">
          <div class="min-w-0">
            <p class="text-[11px] text-slate-400 truncate">{{ selectedPackage ? (locale === 'km' ? selectedPackage.name_km : selectedPackage.name_en) : $t('game.total') }}</p>
            <p class="text-lg font-bold text-sky-300 tabular-nums leading-tight">{{ formatMoney(quote.total_minor, quote.currency) }}</p>
          </div>
          <button
            type="button"
            :disabled="submittingOrder || game.is_in_maintenance"
            class="btn-primary-glow rounded-xl px-5 py-3 text-xs font-bold text-white inline-flex items-center gap-1.5 shrink-0"
            @click="handleCheckout"
          >
            <span>{{ submittingOrder ? $t('game.creatingOrder') : $t('game.checkoutNow') }}</span>
            <ArrowRight class="h-3.5 w-3.5" />
          </button>
        </div>
      </div>
    </div>
  </div>
</template>
