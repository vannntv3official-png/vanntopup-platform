<script setup lang="ts">
import { HelpCircle, ChevronDown, Send, ShieldCheck, Search } from 'lucide-vue-next';

const { locale } = useI18n();
const openIndex = ref<number | null>(0);
const search = ref('');

const faqs = computed(() => [
  {
    q: locale.value === 'km' ? 'តើការបញ្ចូលទឹកប្រាក់ហ្គេមប្រើពេលប៉ុន្មាន?' : 'How fast is automatic top-up delivery?',
    a: locale.value === 'km'
      ? 'បន្ទាប់ពីការទូទាត់តាម Bakong KHQR (ABA, ACLEDA, Wing...) ត្រូវបានផ្ទៀងផ្ទាត់ដោយម៉ាស៊ីនមេ ប្រព័ន្ធស្វ័យប្រវត្តិរបស់យើងនឹងបញ្ចូលពេជ្រ UC ឬ Tokens ទៅគណនីហ្គេមរបស់អ្នកក្នុងរយៈពេល ៣ ទៅ ៣០ វិនាទី។'
      : 'Once your Bakong KHQR payment (ABA, ACLEDA, Wing, or any Cambodian bank) is verified server-to-server, our automated engine delivers Diamonds, UC, or Tokens within 3 to 30 seconds.',
  },
  {
    q: locale.value === 'km' ? 'តើខ្ញុំអាចទូទាត់តាមធនាគារណាខ្លះនៅកម្ពុជា?' : 'Which Cambodian banks and payment apps are supported?',
    a: locale.value === 'km'
      ? 'យើងគាំទ្រ Bakong KHQR ដែលអាចស្កេនទូទាត់បានតាម ABA Mobile, ACLEDA Mobile, Wing Bank, Prince Bank, Canadia Bank និងធនាគារសមាជិកបាគងទាំងអស់ ទាំងប្រាក់ដុល្លារ (USD) និងប្រាក់រៀល (KHR)។'
      : 'We support Bakong KHQR, allowing instant scan-and-pay from ABA Mobile, ACLEDA Mobile, Wing Bank, Prince Bank, Canadia Bank, and all Bakong-connected banks in both USD and KHR.',
  },
  {
    q: locale.value === 'km' ? 'តើខ្ញុំត្រូវផ្តល់ពាក្យសម្ងាត់ (Password) គណនីហ្គេមទេ?' : 'Do I ever need to share my game password?',
    a: locale.value === 'km'
      ? 'ទេ! ដាច់ខាតមិនត្រូវការពាក្យសម្ងាត់ឡើយ។ យើងប្រើតែ User ID / Zone ID ឬ Player ID សាធារណៈរបស់អ្នកតាមរយៈបណ្តាញបញ្ចូលផ្លូវការ ១០០% សុវត្ថិភាព។'
      : 'Never. VannTopUp only requires your public Player ID / User ID and Zone ID through authorized top-up channels — 100% safe for your game account.',
  },
  {
    q: locale.value === 'km' ? 'ចុះបើខ្ញុំបញ្ចូលខុស Player ID តើអាចសងប្រាក់វិញបានទេ?' : 'What if I accidentally enter the wrong Player ID?',
    a: locale.value === 'km'
      ? 'សូមចុចប៊ូតុង "ផ្ទៀងផ្ទាត់គណនី" ដើម្បីពិនិត្យឈ្មោះអ្នកលេងមុនពេលទូទាត់។ ប្រសិនបើការបញ្ចូលត្រូវបានបញ្ជូនជោគជ័យទៅ Player ID ខុសរួចហើយ យើងមិនអាចដកវិញបានទេ។ ប៉ុន្តែបើការបញ្ជូនបរាជ័យ យើងនឹងសងប្រាក់វិញ ១០០%។'
      : 'Always tap "Verify Account" before paying. Completed digital deliveries sent to an incorrect Player ID cannot be reversed, but any definitively failed delivery is eligible for a 100% refund.',
  },
  {
    q: locale.value === 'km' ? 'ចុះបើខ្ញុំបង់ប្រាក់រួចហើយ តែមិនទាន់ឃើញចូលក្នុងហ្គេម?' : 'What if I paid via KHQR but haven\'t received my diamonds yet?',
    a: locale.value === 'km'
      ? 'សូមពិនិត្យស្ថានភាពនៅទំព័រ "តាមដានការបញ្ជាទិញ"។ ប្រសិនបើ Supplier មានការយឺតយ៉ាវ ប្រព័ន្ធនឹងផ្ទៀងផ្ទាត់ស្វ័យប្រវត្តិ (Reconciliation) ឬបញ្ជូនទៅក្រុមជំនួយត្រួតពិនិត្យភ្លាមៗ ដោយធានាមិនកាត់លុយស្ទួនឡើយ។'
      : 'Check your live status on the Track Order page. If a supplier times out, our reconciliation engine verifies delivery status automatically or escalates to our support team — ensuring zero duplicate charges.',
  },
  {
    q: locale.value === 'km' ? 'តើខ្ញុំអាចចុះឈ្មោះធ្វើជា Reseller លក់ដុំបានយ៉ាងដូចម្តេច?' : 'How do I join the Reseller / Wholesale program?',
    a: locale.value === 'km'
      ? 'អ្នកអាចជ្រើសរើសកញ្ចប់ Weekly Wholesale ឬ Monthly Pro Reseller ហើយទាក់ទងមកក្រុមការងារយើងតាម Telegram Support ឬទំព័រទំនាក់ទំនង ដើម្បីបើកតម្លៃដើមពិសេស។'
      : 'Choose our Weekly Wholesale or Monthly Pro Reseller tier and contact our partner team via Telegram Support or the Contact page to activate wholesale pricing.',
  },
]);

const filteredFaqs = computed(() => {
  const q = search.value.trim().toLowerCase();
  if (!q) return faqs.value;
  return faqs.value.filter((item) => item.q.toLowerCase().includes(q) || item.a.toLowerCase().includes(q));
});

function toggle(idx: number) {
  openIndex.value = openIndex.value === idx ? null : idx;
}
</script>

<template>
  <div class="mx-auto max-w-4xl px-4 sm:px-6 py-10 sm:py-14 space-y-8">
    <div class="animate-fade-up space-y-3 text-center max-w-2xl mx-auto">
      <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-500/15 border border-sky-400/30 px-3.5 py-1 text-[11px] font-bold text-sky-300">
        <HelpCircle class="h-3.5 w-3.5" />
        {{ locale === 'km' ? 'មជ្ឈមណ្ឌលសំណួរញឹកញាប់' : 'HELP & FAQ' }}
      </span>
      <h1 class="text-2xl sm:text-4xl font-bold text-white leading-snug">
        {{ locale === 'km' ? 'សំណួរដែលអតិថិជនសួរញឹកញាប់' : 'Frequently Asked Questions' }}
      </h1>
      <div class="relative max-w-md mx-auto pt-2">
        <Search class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2 mt-1" />
        <input
          v-model="search"
          type="search"
          :placeholder="locale === 'km' ? 'ស្វែងរកសំណួរ (KHQR, ល្បឿន, លក់ដុំ...)' : 'Search questions (KHQR, speed, reseller...)'"
          class="w-full rounded-xl border border-white/15 bg-[#0D1528] pl-10 pr-4 py-2.5 text-xs sm:text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:outline-none"
        />
      </div>
    </div>

    <!-- Interactive Accordion -->
    <div class="space-y-3 animate-fade-up" style="animation-delay: 80ms">
      <div
        v-for="(item, idx) in filteredFaqs"
        :key="idx"
        class="glass-card rounded-2xl overflow-hidden transition-colors"
        :class="openIndex === idx ? 'border-sky-400/45' : ''"
      >
        <button
          type="button"
          class="w-full px-5 py-4 text-left flex items-center justify-between gap-4 hover:bg-white/[0.02] transition-colors"
          @click="toggle(idx)"
        >
          <span class="text-sm sm:text-base font-bold text-white leading-snug">{{ item.q }}</span>
          <ChevronDown
            class="h-4 w-4 text-sky-400 shrink-0 transition-transform duration-200"
            :class="openIndex === idx ? 'rotate-180' : ''"
          />
        </button>
        <Transition name="drawer">
          <div v-if="openIndex === idx" class="px-5 pb-5 pt-1 border-t border-white/5 text-xs sm:text-sm text-slate-300 leading-relaxed">
            {{ item.a }}
          </div>
        </Transition>
      </div>
    </div>

    <!-- Still Need Help CTA -->
    <div class="glass-card rounded-3xl p-6 sm:p-8 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 border-sky-400/30">
      <div class="flex items-center gap-3.5">
        <span class="h-11 w-11 rounded-2xl bg-sky-500/15 border border-sky-400/30 flex items-center justify-center shrink-0">
          <ShieldCheck class="h-6 w-6 text-sky-400" />
        </span>
        <div>
          <p class="text-sm sm:text-base font-bold text-white">{{ locale === 'km' ? 'នៅតែមានសំណួរផ្សេងទៀត?' : 'Still have a question?' }}</p>
          <p class="text-xs text-slate-400">{{ locale === 'km' ? 'ក្រុមការងារកម្ពុជាយើងត្រៀមជួយអ្នក ២៤/៧ តាម Telegram។' : 'Our Cambodia support team is ready to assist you on Telegram.' }}</p>
        </div>
      </div>
      <div class="flex gap-2.5 w-full sm:w-auto">
        <a
          href="https://t.me/khmertopup_support"
          target="_blank"
          rel="noopener noreferrer"
          class="flex-1 sm:flex-none inline-flex items-center justify-center gap-2 rounded-xl bg-[#229ED9] hover:bg-[#1b8cc2] px-4 py-2.5 text-xs font-bold text-white"
        >
          <Send class="h-3.5 w-3.5" /> Telegram
        </a>
        <NuxtLink to="/contact" class="flex-1 sm:flex-none inline-flex items-center justify-center rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 px-4 py-2.5 text-xs font-bold text-white">
          {{ $t('nav.contact') }}
        </NuxtLink>
      </div>
    </div>
  </div>
</template>
