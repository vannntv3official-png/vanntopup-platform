<script setup lang="ts">
import { ShieldCheck, FileText, RefreshCcw } from 'lucide-vue-next';

const route = useRoute();
const { locale } = useI18n();
const doc = computed(() => String(route.params.doc));

const docsMeta = computed(() => ({
  terms: {
    icon: FileText,
    title: locale.value === 'km' ? 'លក្ខខណ្ឌប្រើប្រាស់' : 'Terms and Conditions',
    paragraphs: locale.value === 'km'
      ? [
          'VannTopUp ផ្តល់សេវាកម្មបញ្ចូលទឹកប្រាក់ហ្គេមដោយស្វ័យប្រវត្តិសម្រាប់អតិថិជននៅព្រះរាជាណាចក្រកម្ពុជា ទាំងរូបិយប័ណ្ណ USD និង KHR តាមរយៈ Bakong KHQR។',
          'អតិថិជនត្រូវផ្ទៀងផ្ទាត់ User ID / Zone ID ឬ Player ID ឱ្យបានត្រឹមត្រូវមុនពេលបញ្ជាក់ការទូទាត់។ ប្រព័ន្ធនឹងបញ្ជូនទឹកប្រាក់ហ្គេមដោយស្វ័យប្រវត្តិភ្លាមៗបន្ទាប់ពីការទូទាត់ត្រូវបានផ្ទៀងផ្ទាត់ពីធនាគារ។',
          'រាល់ការប៉ុនប៉ងក្លែងបន្លំតម្លៃ ឬប្រើប្រាស់ប្រព័ន្ធខុសគោលបំណងនឹងត្រូវបដិសេធ និងផ្អាកគណនីដោយស្វ័យប្រវត្តិ។',
        ]
      : [
          'VannTopUp operates an automated digital game currency top-up platform serving customers in Cambodia in USD and KHR via Bakong KHQR.',
          'Customers are responsible for verifying their Player ID / Zone ID prior to payment. Automated delivery begins immediately upon server-to-server payment confirmation.',
          'Any attempt to tamper with signed quotes, replay callbacks, or abuse promotions will result in order rejection and account suspension.',
        ],
  },
  privacy: {
    icon: ShieldCheck,
    title: locale.value === 'km' ? 'គោលការណ៍ឯកជនភាព' : 'Privacy Policy',
    paragraphs: locale.value === 'km'
      ? [
          'ព័ត៌មាន Player ID, អ៊ីមែល និងលេខទូរស័ព្ទរបស់អ្នកត្រូវបានអ៊ិនគ្រីប (Encrypted at rest) ហើយប្រើប្រាស់តែសម្រាប់ការបញ្ចូលទឹកប្រាក់ហ្គេម និងជំនួយអតិថិជនប៉ុណ្ណោះ។',
          'យើងមិនដែលស្នើសុំពាក្យសម្ងាត់គណនីហ្គេមរបស់អ្នកឡើយ និងមិនលក់ទិន្នន័យអតិថិជនទៅភាគីទីបីឡើយ។',
          'ការតាមដានការបញ្ជាទិញរបស់ភ្ញៀវ (Guest Order) ត្រូវបានការពារដោយ Secret Tracking Token 64 តួអក្សរ ដើម្បីការពារឯកជនភាពរបស់អ្នក។',
        ]
      : [
          'Your Player IDs, contact details, and security credentials are encrypted at rest and used strictly for order fulfillment and customer support.',
          'We never ask for game account passwords and never sell customer data to third parties.',
          'Guest order tracking is protected by a 64-character cryptographic tracking token so sequential order numbers cannot expose your account details.',
        ],
  },
  refund: {
    icon: RefreshCcw,
    title: locale.value === 'km' ? 'គោលការណ៍សងប្រាក់វិញ' : 'Refund Policy',
    paragraphs: locale.value === 'km'
      ? [
          'ដោយសារទឹកប្រាក់ហ្គេម (Diamonds, UC, Tokens) ជាទំនិញឌីជីថលដែលបញ្ជូនផ្ទាល់ទៅគណនីហ្គេម ការបញ្ជាទិញដែលបានបញ្ចូលជោគជ័យរួចហើយមិនអាចសងប្រាក់វិញបានទេ។',
          'ប្រសិនបើការបញ្ចូលហ្គេមបរាជ័យដោយសារបញ្ហាបច្ចេកទេស ឬ Supplier មិនអាចបញ្ជូនបាន អ្នកមានសិទ្ធិទទួលបានការសងប្រាក់វិញ ១០០% ពេញ។',
          'ក្នុងករណីការបញ្ជូនមានភាពមិនច្បាស់លាស់ (Timeout) ការបញ្ជាទិញនឹងចូលស្ថានភាព Needs Review ដើម្បីឱ្យក្រុមការងារផ្ទៀងផ្ទាត់ជាមួយ Supplier មុនពេលសងប្រាក់ ឬបញ្ជូនម្តងទៀត។',
        ]
      : [
          'Because game currencies (Diamonds, UC, Tokens) are delivered instantly to the target Player ID, successfully completed deliveries are non-refundable.',
          'If delivery definitively fails due to a verified platform or supplier error, customers receive a 100% full refund.',
          'Orders experiencing supplier timeouts safely enter Needs Review for operator reconciliation prior to any retry or refund, preventing double charges.',
        ],
  },
}));

const activeDoc = computed(() => (docsMeta.value as any)[doc.value] || docsMeta.value.terms);
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 sm:px-6 py-10 sm:py-14 space-y-6">
    <!-- Quick Legal Doc Tabs -->
    <div class="flex flex-wrap gap-2">
      <NuxtLink
        v-for="tab in [
          { slug: 'terms', label: $t('footer.terms') },
          { slug: 'privacy', label: $t('footer.privacy') },
          { slug: 'refund', label: $t('footer.refund') },
        ]"
        :key="tab.slug"
        :to="`/legal/${tab.slug}`"
        class="px-4 py-2 rounded-xl text-xs font-bold transition-all"
        :class="doc === tab.slug ? 'btn-primary-glow text-white' : 'border border-white/10 bg-[#0B1224] text-slate-300 hover:text-white'"
      >
        {{ tab.label }}
      </NuxtLink>
    </div>

    <div class="glass-card rounded-3xl p-6 sm:p-9 space-y-5 animate-fade-up">
      <div class="flex items-center gap-3 border-b border-white/10 pb-4">
        <span class="h-10 w-10 rounded-xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center">
          <component :is="activeDoc.icon" class="h-5 w-5 text-sky-400" />
        </span>
        <h1 class="text-2xl sm:text-3xl font-bold text-white">{{ activeDoc.title }}</h1>
      </div>

      <div class="space-y-4 text-sm text-slate-300 leading-relaxed">
        <p v-for="(p, idx) in activeDoc.paragraphs" :key="idx">{{ p }}</p>
      </div>
    </div>
  </div>
</template>
