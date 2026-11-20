<script setup lang="ts">
import { CheckCircle2, AlertCircle, Info } from 'lucide-vue-next';

const { toasts } = useToast();
</script>

<template>
  <TransitionGroup
    name="toast"
    tag="div"
    class="fixed bottom-5 right-5 z-50 flex flex-col gap-2.5 max-w-sm w-full pointer-events-none px-4 sm:px-0"
  >
    <div
      v-for="t in toasts"
      :key="t.id"
      class="pointer-events-auto flex items-center gap-2.5 rounded-xl border px-4 py-3 text-xs font-semibold shadow-2xl backdrop-blur-md"
      :class="{
        'bg-emerald-950/90 border-emerald-500/40 text-emerald-200': t.type === 'success',
        'bg-red-950/90 border-red-500/40 text-red-200': t.type === 'error',
        'bg-slate-900/95 border-brand-accent/40 text-slate-100': t.type === 'info',
      }"
    >
      <CheckCircle2 v-if="t.type === 'success'" class="h-4 w-4 text-emerald-400 shrink-0" />
      <AlertCircle v-else-if="t.type === 'error'" class="h-4 w-4 text-red-400 shrink-0" />
      <Info v-else class="h-4 w-4 text-brand-accent shrink-0" />
      <span>{{ t.message }}</span>
    </div>
  </TransitionGroup>
</template>
