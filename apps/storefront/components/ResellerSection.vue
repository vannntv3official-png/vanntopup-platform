<script setup lang="ts">
import { formatMoney } from '@topup/shared-types';
import { Crown, CheckCircle2, Globe, Sparkles, ArrowRight, TrendingUp, Zap, ShieldCheck } from 'lucide-vue-next';
import { usePreferencesStore } from '~/stores/preferences';

const { locale } = useI18n();
const prefs = usePreferencesStore();

function formatPlanPrice(usdCents: number | null): string {
  if (usdCents === null) {
    return locale.value === 'km' ? 'ទំនាក់ទំនង' : 'Custom';
  }
  if (prefs.currency === 'KHR') {
    const khr = Math.ceil((usdCents / 100) * prefs.exchangeRateUsdToKhr);
    return formatMoney(khr, 'KHR');
  }
  return formatMoney(usdCents, 'USD');
}

const highlights = computed(() => [
  {
    icon: TrendingUp,
    title: locale.value === 'km' ? 'តម្លៃដើមទាបជាងទីផ្សារ' : 'Fixed Wholesale Margins',
    desc: locale.value === 'km' ? 'បង្កើនប្រាក់ចំណេញរាល់ការបញ្ចូល' : 'Higher profit on every top-up',
  },
  {
    icon: Zap,
    title: locale.value === 'km' ? 'ប្រព័ន្ធស្វ័យប្រវត្តិ ២៤/៧' : '24/7 Automated Engine',
    desc: locale.value === 'km' ? 'បញ្ចូលក្នុង ៣ វិនាទី តាម KHQR' : '3-second Bakong KHQR delivery',
  },
  {
    icon: ShieldCheck,
    title: locale.value === 'km' ? 'គ្មានកម្រិតបញ្ជាទិញអប្បបរមា' : 'Zero Minimum Volume',
    desc: locale.value === 'km' ? 'ចាប់ផ្តើមលក់បានភ្លាមៗថ្ងៃនេះ' : 'Start selling from order #1',
  },
]);

const plans = computed(() => [
  {
    id: 'weekly',
    name: locale.value === 'km' ? 'សមាជិកលក់ដុំប្រចាំសប្តាហ៍' : 'Weekly Wholesale',
    usdCents: 499,
    period: locale.value === 'km' ? '/ ៧ ថ្ងៃ' : '/ 7 days',
    badge: locale.value === 'km' ? 'ចាប់ផ្តើម' : 'STARTER',
    highlighted: false,
    description: locale.value === 'km'
      ? 'ស័ក្តិសមសម្រាប់អ្នកចាប់ផ្តើមលក់បន្ត ឬអ្នកទិញពេជ្រញឹកញាប់។'
      : 'Ideal for new game resellers and frequent buyers testing wholesale rates.',
    features: locale.value === 'km'
      ? [
          'តម្លៃដើមពិសេសលើគ្រប់ហ្គេមទាំងអស់',
          'បញ្ចូលស្វ័យប្រវត្តិ ២៤/៧ តាម KHQR',
          'ជំនួយអតិថិជនរហ័សតាម Telegram',
        ]
      : [
          'Discounted wholesale pricing on all 4 games',
          '24/7 automated delivery via Bakong KHQR',
          'Fast Telegram reseller support',
        ],
    cta: locale.value === 'km' ? 'ស្នើសុំកញ្ចប់សប្តាហ៍' : 'Start Weekly Pass',
  },
  {
    id: 'monthly',
    name: locale.value === 'km' ? 'Reseller Pro ប្រចាំខែ' : 'Monthly Pro Reseller',
    usdCents: 1499,
    period: locale.value === 'km' ? '/ ៣០ ថ្ងៃ' : '/ 30 days',
    badge: locale.value === 'km' ? 'ពេញនិយមបំផុត' : 'MOST POPULAR',
    highlighted: true,
    description: locale.value === 'km'
      ? 'តម្លៃលក់ដុំទាបបំផុតសម្រាប់ហាងលក់ហ្គេម និងតំណាងចែកចាយនៅកម្ពុជា។'
      : 'Lowest VIP wholesale margins built for active Cambodian top-up shops.',
    features: locale.value === 'km'
      ? [
          'តម្លៃដើមទាបបំផុតកម្រិត VIP Reseller',
          'របាយការណ៍ប្រវត្តិបញ្ជាទិញ និងប្រាក់ចំណេញច្បាស់លាស់',
          'ការផ្ទៀងផ្ទាត់អាទិភាពពេលមានបញ្ហា Supplier',
          'គ្មានកម្រិតចំនួនបញ្ជាទិញប្រចាំថ្ងៃ',
        ]
      : [
          'Lowest VIP reseller tier pricing across catalog',
          'Full order history & gross profit tracking',
          'Priority reconciliation & dedicated Telegram care',
          'Unlimited daily automated top-up orders',
        ],
    cta: locale.value === 'km' ? 'ក្លាយជា Pro Reseller' : 'Upgrade to Pro Reseller',
  },
  {
    id: 'whitelabel',
    name: locale.value === 'km' ? 'វេបសាយផ្ទាល់ខ្លួន' : 'Custom Domain Store',
    usdCents: null,
    period: '',
    badge: locale.value === 'km' ? 'អាជីវកម្ម' : 'WHITE-LABEL',
    highlighted: false,
    description: locale.value === 'km'
      ? 'បើកវេបសាយបញ្ចូលហ្គេមផ្ទាល់ខ្លួនក្រោមឈ្មោះ Brand និង Domain របស់អ្នក។'
      : 'Launch your own branded game top-up website on your custom domain.',
    features: locale.value === 'km'
      ? [
          'ឈ្មោះ Domain ផ្ទាល់ខ្លួន (.com / .kh)',
          'ភ្ជាប់ប្រព័ន្ធបញ្ចូលស្វ័យប្រវត្តិ និង KHQR រួចជាស្រេច',
          'ផ្ទាំង Admin គ្រប់គ្រងតម្លៃ និងអតិថិជនដោយខ្លួនឯង',
        ]
      : [
          'Your own custom domain & brand identity',
          'Turnkey automated fulfillment & KHQR checkout',
          'Dedicated Admin Console to set your own prices',
        ],
    cta: locale.value === 'km' ? 'ទំនាក់ទំនងក្រុមការងារ' : 'Contact Partner Team',
  },
]);
</script>

<template>
  <section class="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8 py-12 sm:py-16">
    <div class="relative overflow-hidden rounded-3xl border border-white/10 bg-gradient-to-b from-[#0E172E]/90 via-[#091022]/95 to-[#070B16] p-5 sm:p-8 lg:p-10 shadow-2xl">
      <!-- Subtle Ambient Glow -->
      <div class="absolute -top-24 left-1/2 -translate-x-1/2 w-[480px] h-[220px] rounded-full bg-blue-600/20 blur-[100px] pointer-events-none" />

      <!-- Header -->
      <div class="relative z-10 text-center max-w-2xl mx-auto space-y-3 mb-8 sm:mb-10">
        <span class="inline-flex items-center gap-1.5 rounded-full bg-amber-400/15 border border-amber-400/35 px-3.5 py-1 text-[11px] font-extrabold tracking-wider text-amber-300">
          <Crown class="h-3.5 w-3.5" />
          {{ locale === 'km' ? 'កម្មវិធីតំណាងចែកចាយ & លក់ដុំ' : 'RESELLER & WHOLESALE PROGRAM' }}
        </span>
        <h2 class="text-2xl sm:text-3xl lg:text-4xl font-extrabold text-white tracking-tight leading-tight">
          {{ locale === 'km' ? 'ចាប់ផ្តើមអាជីវកម្មលក់ទឹកប្រាក់ហ្គេមជាមួយយើង' : 'Grow Your Top-Up Business With Wholesale Rates' }}
        </h2>
        <p class="text-xs sm:text-sm text-slate-300/90 leading-relaxed">
          {{ locale === 'km'
            ? 'ទទួលបានតម្លៃដើមទាបជាងទីផ្សារ ប្រព័ន្ធបញ្ចូលស្វ័យប្រវត្តិ ២៤ ម៉ោង និងជំនួយបច្ចេកទេសផ្ទាល់នៅកម្ពុជា។'
            : 'Unlock fixed wholesale pricing, automated 24/7 supplier routing, or launch your own branded custom-domain storefront.' }}
        </p>
      </div>

      <!-- 3 Compact Value Highlights -->
      <div class="relative z-10 grid grid-cols-1 sm:grid-cols-3 gap-3 mb-8 sm:mb-10">
        <div
          v-for="(item, idx) in highlights"
          :key="idx"
          class="rounded-2xl border border-white/10 bg-white/[0.03] px-4 py-3.5 flex items-center gap-3"
        >
          <span class="h-9 w-9 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center shrink-0">
            <component :is="item.icon" class="h-4 w-4 text-sky-400" />
          </span>
          <div class="min-w-0">
            <p class="text-xs font-bold text-white truncate">{{ item.title }}</p>
            <p class="text-[11px] text-slate-400 truncate">{{ item.desc }}</p>
          </div>
        </div>
      </div>

      <!-- 3 Tier Cards -->
      <div class="relative z-10 grid grid-cols-1 lg:grid-cols-3 gap-5 sm:gap-6 items-stretch">
        <div
          v-for="plan in plans"
          :key="plan.id"
          class="relative rounded-3xl p-5 sm:p-7 flex flex-col justify-between gap-6 transition-all duration-300"
          :class="plan.highlighted
            ? 'bg-gradient-to-b from-blue-600/30 via-[#121E3D] to-[#0A1124] border-2 border-sky-400/80 shadow-2xl shadow-blue-900/50 lg:-translate-y-2'
            : 'bg-[#0A1124]/90 border border-white/10 hover:border-sky-400/40'"
        >
          <!-- Glowing Top Accent Line on Popular Tier -->
          <div
            v-if="plan.highlighted"
            class="absolute inset-x-8 top-0 h-1 rounded-b-full bg-gradient-to-r from-blue-500 via-sky-300 to-blue-500"
          />

          <div class="space-y-4">
            <div class="flex items-center justify-between gap-2">
              <span
                class="rounded-full px-2.5 py-0.5 text-[10px] font-extrabold tracking-wider uppercase"
                :class="plan.highlighted
                  ? 'bg-sky-400 text-slate-950'
                  : 'bg-white/10 text-sky-300 border border-white/10'"
              >
                {{ plan.badge }}
              </span>
              <Crown v-if="plan.highlighted" class="h-5 w-5 text-amber-400" />
            </div>

            <div>
              <h3 class="text-lg sm:text-xl font-extrabold text-white">{{ plan.name }}</h3>
              <div class="flex items-baseline gap-1.5 mt-2">
                <span class="text-3xl sm:text-4xl font-black text-white tabular-nums">
                  {{ formatPlanPrice(plan.usdCents) }}
                </span>
                <span v-if="plan.period" class="text-xs text-slate-400 font-semibold">{{ plan.period }}</span>
              </div>
            </div>

            <p class="text-xs text-slate-300 leading-relaxed">{{ plan.description }}</p>

            <ul class="space-y-2.5 pt-3 border-t border-white/10">
              <li v-for="(feat, idx) in plan.features" :key="idx" class="flex items-start gap-2.5 text-xs text-slate-200">
                <CheckCircle2 class="h-4 w-4 text-emerald-400 shrink-0 mt-0.5" />
                <span>{{ feat }}</span>
              </li>
            </ul>
          </div>

          <NuxtLink
            to="/contact"
            class="w-full inline-flex items-center justify-center gap-2 rounded-2xl py-3.5 text-xs sm:text-sm font-extrabold transition-all active:scale-[0.98]"
            :class="plan.highlighted
              ? 'btn-primary-glow text-white'
              : 'border border-white/15 bg-white/5 hover:bg-white/10 hover:border-sky-400/50 text-white'"
          >
            <Globe v-if="plan.id === 'whitelabel'" class="h-4 w-4 text-sky-400" />
            <Sparkles v-else-if="plan.highlighted" class="h-4 w-4" />
            <span>{{ plan.cta }}</span>
            <ArrowRight class="h-4 w-4" />
          </NuxtLink>
        </div>
      </div>
    </div>
  </section>
</template>
