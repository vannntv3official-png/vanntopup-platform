<script setup lang="ts">
import {
  Mail, MessageSquare, Send, Clock, MapPin, Headphones,
  CheckCircle2, Loader2, Tag, Sparkles
} from 'lucide-vue-next';

const { request } = useApi();
const { locale } = useI18n();
const toast = useToast();

const email = ref('');
const subject = ref('');
const message = ref('');
const submitting = ref(false);
const submitted = ref(false);
const error = ref<string | null>(null);

const quickTopics = computed(() => locale.value === 'km'
  ? ['បញ្ហាការទូទាត់ KHQR', 'ការបញ្ចូលហ្គេមយឺត', 'ស្នើសុំធ្វើ Reseller លក់ដុំ', 'សំណួរទូទៅ']
  : ['KHQR Payment Issue', 'Delayed Top-Up', 'Reseller / Wholesale Inquiry', 'General Question'],
);

async function submit() {
  submitting.value = true;
  error.value = null;
  try {
    await request('/support/tickets', {
      method: 'POST',
      body: { contact_email: email.value, subject: subject.value, message: message.value },
    });
    submitted.value = true;
    toast.success(locale.value === 'km' ? 'បានផ្ញើសារទៅក្រុមជំនួយជោគជ័យ!' : 'Support ticket submitted!');
  } catch (err: any) {
    error.value = err?.data?.message || 'Unable to submit support request.';
  } finally {
    submitting.value = false;
  }
}

function resetForm() {
  subject.value = '';
  message.value = '';
  submitted.value = false;
}
</script>

<template>
  <div class="mx-auto max-w-6xl px-4 sm:px-6 py-10 sm:py-14 space-y-8">
    <!-- Page Header -->
    <div class="animate-fade-up max-w-2xl space-y-2">
      <span class="inline-flex items-center gap-1.5 rounded-full bg-sky-500/15 border border-sky-400/30 px-3 py-1 text-[11px] font-bold text-sky-300">
        <Headphones class="h-3.5 w-3.5" />
        {{ locale === 'km' ? 'មជ្ឈមណ្ឌលជំនួយអតិថិជន' : 'CAMBODIA SUPPORT CENTER' }}
      </span>
      <h1 class="text-3xl sm:text-4xl font-extrabold text-white tracking-tight">
        {{ locale === 'km' ? 'ទំនាក់ទំនងក្រុមការងារ VannTopUp' : 'Get Fast Help From VannTopUp' }}
      </h1>
      <p class="text-sm text-slate-400 leading-relaxed">
        {{ locale === 'km'
          ? 'មានសំណួរអំពីការបញ្ជាទិញ ការទូទាត់ KHQR ឬចង់សហការធ្វើជាតំណាងចែកចាយ? យើងរង់ចាំជួយអ្នកជានិច្ច។'
          : 'Questions about an order, KHQR verification, or our wholesale reseller plans? Reach out anytime.' }}
      </p>
    </div>

    <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">
      <!-- Left Column: Direct Channels -->
      <div class="lg:col-span-5 space-y-4 animate-fade-up" style="animation-delay: 60ms">
        <!-- Featured Telegram Direct Support -->
        <div class="rounded-3xl border border-sky-400/40 bg-gradient-to-br from-[#229ED9]/25 via-[#101C36] to-[#0A1020] p-6 space-y-4 shadow-xl">
          <div class="flex items-center justify-between">
            <span class="h-11 w-11 rounded-2xl bg-[#229ED9] flex items-center justify-center shadow-lg shadow-sky-500/30">
              <Send class="h-5 w-5 text-white" />
            </span>
            <span class="inline-flex items-center gap-1.5 rounded-full bg-emerald-500/20 border border-emerald-400/30 px-2.5 py-1 text-[11px] font-bold text-emerald-300">
              <span class="h-2 w-2 rounded-full bg-emerald-400 animate-pulse" />
              {{ locale === 'km' ? 'ឆ្លើយតប ~៥ នាទី' : '~5 min reply' }}
            </span>
          </div>
          <div>
            <h2 class="text-lg font-extrabold text-white">Telegram Support 24/7</h2>
            <p class="text-xs text-slate-300 mt-1 leading-relaxed">
              {{ locale === 'km'
                ? 'វិធីលឿនបំផុតដើម្បីដោះស្រាយបញ្ហាការបញ្ជាទិញ ឬស្នើសុំកញ្ចប់ Reseller លក់ដុំ។'
                : 'Fastest way to resolve order issues, check delayed top-ups, or activate reseller pricing.' }}
            </p>
          </div>
          <a
            href="https://t.me/khmertopup_support"
            target="_blank"
            rel="noopener noreferrer"
            class="w-full inline-flex items-center justify-center gap-2 rounded-xl bg-[#229ED9] hover:bg-[#1b8cc2] py-3 text-xs font-extrabold text-white transition-all active:scale-[0.98]"
          >
            <Send class="h-4 w-4" />
            <span>{{ locale === 'km' ? 'ឆាតតាម Telegram ឥឡូវនេះ' : 'Chat on Telegram Now' }}</span>
          </a>
        </div>

        <!-- Operating Hours & Location Cards -->
        <div class="glass-card rounded-2xl p-5 flex items-start gap-3.5">
          <span class="h-10 w-10 rounded-xl bg-amber-500/15 border border-amber-400/30 flex items-center justify-center shrink-0">
            <Clock class="h-5 w-5 text-amber-400" />
          </span>
          <div>
            <p class="text-sm font-bold text-white">{{ locale === 'km' ? 'ម៉ោងធ្វើការ' : 'Support Hours' }}</p>
            <p class="text-xs text-slate-400 mt-0.5">
              {{ locale === 'km' ? 'ប្រព័ន្ធបញ្ចូលស្វ័យប្រវត្តិ៖ ២៤/៧ • បុគ្គលិកជំនួយ៖ 08:00 – 23:00 (ICT)' : 'Auto Top-Up: 24/7 • Live Agents: 08:00 – 23:00 ICT (Phnom Penh)' }}
            </p>
          </div>
        </div>

        <div class="glass-card rounded-2xl p-5 flex items-start gap-3.5">
          <span class="h-10 w-10 rounded-xl bg-blue-500/15 border border-blue-400/30 flex items-center justify-center shrink-0">
            <MapPin class="h-5 w-5 text-sky-400" />
          </span>
          <div>
            <p class="text-sm font-bold text-white">{{ locale === 'km' ? 'ទីតាំងប្រតិបត្តិការ' : 'Operations Base' }}</p>
            <p class="text-xs text-slate-400 mt-0.5">Phnom Penh, Kingdom of Cambodia 🇰🇭</p>
          </div>
        </div>
      </div>

      <!-- Right Column: Polished Support Ticket Form -->
      <div class="lg:col-span-7 glass-card rounded-3xl p-6 sm:p-8 animate-fade-up" style="animation-delay: 120ms">
        <div v-if="submitted" class="animate-success-burst py-8 text-center space-y-4">
          <div class="mx-auto h-14 w-14 rounded-2xl bg-emerald-500/20 border border-emerald-400/40 flex items-center justify-center">
            <CheckCircle2 class="h-8 w-8 text-emerald-400" />
          </div>
          <h2 class="text-xl font-extrabold text-white">
            {{ locale === 'km' ? 'យើងបានទទួលសាររបស់អ្នកហើយ!' : 'Support Ticket Created!' }}
          </h2>
          <p class="text-xs sm:text-sm text-slate-300 max-w-md mx-auto">
            {{ locale === 'km' ? 'ក្រុមការងាររបស់យើងនឹងឆ្លើយតបទៅអ៊ីមែលរបស់អ្នកក្នុងពេលឆាប់ៗនេះ។' : 'Our Cambodia support team will reply to your email shortly.' }}
          </p>
          <button
            type="button"
            class="rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 px-5 py-2.5 text-xs font-bold text-white"
            @click="resetForm"
          >
            {{ locale === 'km' ? 'ផ្ញើសារមួយទៀត' : 'Send Another Message' }}
          </button>
        </div>

        <form v-else class="space-y-5" @submit.prevent="submit">
          <div class="flex items-center justify-between">
            <h2 class="text-lg font-extrabold text-white flex items-center gap-2">
              <Sparkles class="h-4 w-4 text-sky-400" />
              <span>{{ locale === 'km' ? 'បើកសំបុត្រជំនួយ (Support Ticket)' : 'Send a Support Message' }}</span>
            </h2>
          </div>

          <!-- Quick Topic Chips -->
          <div class="space-y-1.5">
            <label class="block text-[11px] font-semibold text-slate-400">{{ locale === 'km' ? 'ជ្រើសប្រធានបទរហ័ស៖' : 'Quick topic:' }}</label>
            <div class="flex flex-wrap gap-2">
              <button
                v-for="topic in quickTopics"
                :key="topic"
                type="button"
                class="px-3 py-1.5 rounded-xl text-[11px] font-bold border transition-all active:scale-95"
                :class="subject === topic
                  ? 'bg-sky-500/20 border-sky-400 text-sky-300'
                  : 'bg-[#070B16] border-white/10 text-slate-400 hover:text-white hover:border-white/25'"
                @click="subject = topic"
              >
                {{ topic }}
              </button>
            </div>
          </div>

          <div v-if="error" class="rounded-xl bg-red-500/15 border border-red-500/40 p-3 text-xs text-red-200">
            {{ error }}
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-300">{{ locale === 'km' ? 'អ៊ីមែលរបស់អ្នក' : 'Your Email' }}</label>
            <div class="relative">
              <Mail class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model="email"
                type="email"
                required
                placeholder="you@example.com"
                class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:outline-none"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-300">{{ locale === 'km' ? 'ប្រធានបទ' : 'Subject' }}</label>
            <div class="relative">
              <Tag class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
              <input
                v-model="subject"
                type="text"
                required
                minlength="4"
                :placeholder="locale === 'km' ? 'ឧ. បញ្ហាការទូទាត់ ឬលេខការបញ្ជាទិញ KTU-...' : 'e.g. Order KTU-... or Reseller inquiry'"
                class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:outline-none"
              />
            </div>
          </div>

          <div class="space-y-1.5">
            <label class="block text-xs font-semibold text-slate-300">{{ locale === 'km' ? 'ព័ត៌មានលម្អិត' : 'Message Details' }}</label>
            <div class="relative">
              <MessageSquare class="h-4 w-4 text-slate-400 absolute left-3.5 top-3.5" />
              <textarea
                v-model="message"
                rows="4"
                required
                minlength="10"
                :placeholder="locale === 'km' ? 'សូមរៀបរាប់អំពីបញ្ហា ឬសំណួររបស់អ្នក...' : 'Describe how we can help you (include Order Number if applicable)...'"
                class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:outline-none"
              />
            </div>
          </div>

          <button
            type="submit"
            :disabled="submitting"
            class="w-full btn-primary-glow disabled:opacity-50 rounded-2xl py-3.5 text-sm font-extrabold text-white inline-flex items-center justify-center gap-2"
          >
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <Send v-else class="h-4 w-4" />
            <span>{{ locale === 'km' ? 'ផ្ញើសារជំនួយ' : 'Send Support Message' }}</span>
          </button>
        </form>
      </div>
    </div>
  </div>
</template>
