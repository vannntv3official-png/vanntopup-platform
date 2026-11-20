<script setup lang="ts">
import { Send, X, Headphones, Search, MessageCircle } from 'lucide-vue-next';

const { locale } = useI18n();
const route = useRoute();
const open = ref(false);
const isGameDetailPage = computed(() => route.path.startsWith('/games/') && route.path !== '/games');
</script>

<template>
  <div
    class="fixed bottom-24 md:bottom-6 right-4 md:right-5 z-40 flex-col items-end gap-3"
    :class="isGameDetailPage ? 'hidden md:flex' : 'flex'"
  >
    <!-- Expandable Support Popover -->
    <Transition name="pop">
      <div
        v-if="open"
        class="w-72 rounded-2xl border border-sky-400/35 bg-[#0D1528]/95 backdrop-blur-xl p-4 shadow-2xl space-y-3"
      >
        <div class="flex items-center justify-between border-b border-white/10 pb-2.5">
          <div class="flex items-center gap-2">
            <span class="h-2.5 w-2.5 rounded-full bg-emerald-400 animate-pulse" />
            <p class="text-xs font-extrabold text-white">
              {{ locale === 'km' ? 'ជំនួយអតិថិជន ២៤/៧' : '24/7 Cambodia Support' }}
            </p>
          </div>
          <button type="button" class="text-slate-400 hover:text-white" aria-label="Close support menu" @click="open = false">
            <X class="h-4 w-4" />
          </button>
        </div>

        <p class="text-[11px] text-slate-300 leading-relaxed">
          {{ locale === 'km'
            ? 'មានបញ្ហាការទូទាត់ KHQR ឬចង់សួរអំពីការបញ្ជាទិញ? ទាក់ទងក្រុមការងារយើងតាម Telegram ភ្លាមៗ!'
            : 'Need help with a KHQR payment, delayed top-up, or reseller plan? Reach our team instantly.' }}
        </p>

        <div class="space-y-2">
          <a
            href="https://t.me/khmertopup_support"
            target="_blank"
            rel="noopener noreferrer"
            class="flex items-center gap-2.5 rounded-xl bg-[#229ED9] hover:bg-[#1d8ec4] px-3.5 py-2.5 text-xs font-bold text-white transition-all active:scale-[0.98]"
          >
            <Send class="h-4 w-4" />
            <span>{{ locale === 'km' ? 'ឆាតតាម Telegram Support' : 'Chat on Telegram Support' }}</span>
          </a>

          <NuxtLink
            to="/track"
            class="flex items-center gap-2.5 rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 px-3.5 py-2.5 text-xs font-semibold text-slate-200 transition-colors"
            @click="open = false"
          >
            <Search class="h-4 w-4 text-sky-400" />
            <span>{{ locale === 'km' ? 'តាមដានការបញ្ជាទិញ' : 'Track My Order' }}</span>
          </NuxtLink>

          <NuxtLink
            to="/contact"
            class="flex items-center gap-2.5 rounded-xl border border-white/15 bg-white/5 hover:bg-white/10 px-3.5 py-2.5 text-xs font-semibold text-slate-200 transition-colors"
            @click="open = false"
          >
            <Headphones class="h-4 w-4 text-amber-400" />
            <span>{{ locale === 'km' ? 'បើកសំបុត្រជំនួយ (Ticket)' : 'Open Support Ticket' }}</span>
          </NuxtLink>
        </div>
      </div>
    </Transition>

    <!-- Floating Telegram Action Button -->
    <button
      type="button"
      class="group relative flex items-center gap-2.5 rounded-full bg-gradient-to-r from-[#229ED9] to-[#2563EB] hover:brightness-110 pl-4 pr-4 py-3 text-xs font-extrabold text-white shadow-2xl shadow-sky-500/35 transition-all active:scale-95 animate-float"
      aria-label="Open Telegram Support"
      @click="open = !open"
    >
      <span class="absolute -inset-1 rounded-full bg-sky-400/25 blur-md opacity-70 group-hover:opacity-100 pointer-events-none" />
      <MessageCircle v-if="!open" class="h-5 w-5 relative z-10" />
      <X v-else class="h-5 w-5 relative z-10" />
      <span class="relative z-10 hidden sm:inline">
        {{ locale === 'km' ? 'Telegram ជំនួយ' : 'Telegram' }}
      </span>
    </button>
  </div>
</template>
