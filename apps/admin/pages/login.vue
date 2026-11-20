<script setup lang="ts">
import type { AuthUser } from '@topup/shared-types';
import { Mail, Lock, Eye, EyeOff, KeyRound, Loader2, Sparkles } from 'lucide-vue-next';
import { useAdminAuthStore } from '~/stores/adminAuth';

const router = useRouter();
const auth = useAdminAuthStore();
const { request } = useAdminApi();
const toast = useAdminToast();

const email = ref('admin@khmertopup.local');
const password = ref('');
const mfaCode = ref('');
const mfaRequired = ref(false);
const showPassword = ref(false);
const error = ref<string | null>(null);
const loading = ref(false);

function fillDemoAdmin() {
  email.value = 'admin@khmertopup.local';
  password.value = 'AdminPass123!';
  toast.info('Demo Super Admin credentials filled');
}

async function submitLogin() {
  loading.value = true;
  error.value = null;
  try {
    const res = await request<{ token: string; user: AuthUser }>('/auth/login', {
      method: 'POST',
      body: {
        email: email.value,
        password: password.value,
        mfa_code: mfaCode.value || undefined,
      },
    });
    auth.setSession(res.token, res.user);
    toast.success('Signed in to VannTopUp Admin');
    await router.push('/');
  } catch (err: any) {
    if (err?.data?.mfa_required) {
      mfaRequired.value = true;
      error.value = 'Enter your 6-digit TOTP MFA code to continue.';
    } else {
      error.value = err?.data?.message || 'Admin login failed.';
    }
  } finally {
    loading.value = false;
  }
}
</script>

<template>
  <div class="min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md admin-card rounded-3xl p-7 sm:p-9 space-y-6 relative overflow-hidden">
      <div class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-sky-400 to-transparent" />

      <div class="flex items-center gap-3.5">
        <img src="/images/brand/vanntopup-logo.jpg" alt="VannTopUp Logo" class="h-12 w-12 rounded-2xl object-cover border border-sky-400/40 shadow-lg" />
        <div>
          <h1 class="text-xl font-bold text-white">Vann<span class="text-sky-400">TopUp</span> Admin</h1>
          <p class="text-xs text-slate-400">Restricted to authorized operators</p>
        </div>
      </div>

      <div v-if="error" class="rounded-xl bg-red-500/15 border border-red-500/40 p-3 text-xs text-red-200">
        {{ error }}
      </div>

      <form class="space-y-4" @submit.prevent="submitLogin">
        <div class="space-y-1.5">
          <label class="block text-xs font-medium text-slate-300">Admin Email</label>
          <div class="relative">
            <Mail class="h-4 w-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input v-model="email" type="email" required class="w-full rounded-xl border border-white/15 bg-[#060A15] pl-10 pr-4 py-3 text-sm text-white focus:border-sky-400 focus:outline-none" />
          </div>
        </div>

        <div class="space-y-1.5">
          <label class="block text-xs font-medium text-slate-300">Password</label>
          <div class="relative">
            <Lock class="h-4 w-4 text-slate-500 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input v-model="password" :type="showPassword ? 'text' : 'password'" required class="w-full rounded-xl border border-white/15 bg-[#060A15] pl-10 pr-11 py-3 text-sm text-white focus:border-sky-400 focus:outline-none" />
            <button type="button" class="absolute right-3.5 top-1/2 -translate-y-1/2 text-slate-400 hover:text-white" @click="showPassword = !showPassword">
              <EyeOff v-if="showPassword" class="h-4 w-4" />
              <Eye v-else class="h-4 w-4" />
            </button>
          </div>
        </div>

        <div v-if="mfaRequired" class="space-y-1.5">
          <label class="block text-xs font-medium text-sky-300">6-Digit TOTP MFA Code</label>
          <div class="relative">
            <KeyRound class="h-4 w-4 text-sky-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
            <input v-model="mfaCode" type="text" maxlength="6" required class="w-full rounded-xl border border-sky-400/50 bg-[#060A15] pl-10 pr-4 py-3 text-sm font-mono text-white focus:outline-none" />
          </div>
        </div>

        <button type="submit" :disabled="loading" class="w-full btn-admin-primary disabled:opacity-50 rounded-2xl py-3.5 text-sm font-bold text-white inline-flex items-center justify-center gap-2">
          <Loader2 v-if="loading" class="h-4 w-4 animate-spin" />
          <span>{{ loading ? 'Signing in...' : 'Sign In to Console' }}</span>
        </button>
      </form>

      <div class="pt-3 border-t border-white/10 text-center">
        <button type="button" class="inline-flex items-center gap-1.5 text-xs font-bold text-sky-400 hover:text-sky-300" @click="fillDemoAdmin">
          <Sparkles class="h-3.5 w-3.5 text-amber-400" /> Fill Demo Super Admin
        </button>
      </div>
    </div>
  </div>
</template>
