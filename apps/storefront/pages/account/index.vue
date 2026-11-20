<script setup lang="ts">
import { formatMoney, type AuthUser, type OrderSummary } from '@topup/shared-types';
import {
  Mail, Lock, User, Phone, Eye, EyeOff, ShieldCheck, Zap, History,
  LogOut, ArrowRight, ArrowLeft, Loader2, Sparkles, CheckCircle2, Settings,
  Headphones, KeyRound, Bell, Save, Copy, Check
} from 'lucide-vue-next';
import { useAuthStore } from '~/stores/auth';
import { usePreferencesStore } from '~/stores/preferences';

const { request } = useApi();
const { locale, setLocale } = useI18n();
const route = useRoute();
const router = useRouter();
const auth = useAuthStore();
const prefs = usePreferencesStore();
const toast = useToast();
const googleLoading = ref(false);

const mode = ref<'login' | 'register'>('login');
const email = ref('');
const password = ref('');
const passwordConfirmation = ref('');
const name = ref('');
const phone = ref('');
const loginMfaCode = ref('');
const loginMfaRequired = ref(false);
const showPassword = ref(false);
const showConfirmPassword = ref(false);
const trustDevice = ref(true);
const submitting = ref(false);
const error = ref<string | null>(null);

const passwordStrength = computed(() => {
  const pw = password.value;
  if (!pw) return 0;
  let score = 0;
  if (pw.length >= 10) score += 1;
  if (/[A-Z]/.test(pw) && /[a-z]/.test(pw)) score += 1;
  if (/\d/.test(pw)) score += 1;
  if (/[^A-Za-z0-9]/.test(pw)) score += 1;
  return score;
});

// Authenticated Dashboard State
const activeTab = ref<'orders' | 'profile' | 'tickets' | 'security'>('orders');
const orders = ref<OrderSummary[]>([]);
const tickets = ref<any[]>([]);

// Profile Edit Form
const profileName = ref('');
const profilePhone = ref('');
const profileLocale = ref<'km' | 'en'>('km');
const profileCurrency = ref<'USD' | 'KHR'>('USD');
const notifyEmail = ref(true);
const notifyInApp = ref(true);
const notifyTelegram = ref(false);
const savingProfile = ref(false);

// MFA Setup State
const mfaSecret = ref<string | null>(null);
const mfaCode = ref('');
const settingUpMfa = ref(false);
const copiedSecret = ref(false);

const quickGames = [
  { slug: 'mobile-legends', name: 'Mobile Legends', logo: '/images/games/icons/mobile-legends-logo.jpg' },
  { slug: 'free-fire', name: 'Free Fire', logo: '/images/games/icons/free-fire-logo.jpg' },
  { slug: 'pubg-mobile', name: 'PUBG Mobile', logo: '/images/games/icons/pubg-mobile-logo.jpg' },
  { slug: 'honor-of-kings', name: 'Honor of Kings', logo: '/images/games/icons/honor-of-kings-logo.jpg' },
];

const logoBySlug: Record<string, string> = {
  'mobile-legends': '/images/games/icons/mobile-legends-logo.jpg',
  'free-fire': '/images/games/icons/free-fire-logo.jpg',
  'pubg-mobile': '/images/games/icons/pubg-mobile-logo.jpg',
  'honor-of-kings': '/images/games/icons/honor-of-kings-logo.jpg',
};

const completedOrdersCount = computed(() => orders.value.filter((o) => o.status === 'completed').length);

async function loadAccount() {
  if (!auth.token) return;
  try {
    const [me, ord, tkt] = await Promise.all([
      request<{ user: AuthUser }>('/auth/me'),
      request<{ data: OrderSummary[] }>('/account/orders'),
      request<{ data: any[] }>('/account/tickets').catch(() => ({ data: [] })),
    ]);
    auth.user = me.user;
    orders.value = ord.data;
    tickets.value = tkt.data || [];

    profileName.value = me.user.name || '';
    profilePhone.value = me.user.phone || '';
    profileLocale.value = (me.user.preferred_locale as 'km' | 'en') || 'km';
    profileCurrency.value = (me.user.preferred_currency as 'USD' | 'KHR') || 'USD';
  } catch {
    auth.clearSession();
  }
}

onMounted(async () => {
  const oauthToken = String(route.query.oauth_token || '');
  const oauthError = String(route.query.oauth_error || '');

  if (oauthToken) {
    auth.token = oauthToken;
    if (import.meta.client) {
      localStorage.setItem('ktu_customer_token', oauthToken);
    }
    await router.replace('/account');
    await loadAccount();
    toast.success(locale.value === 'km' ? 'ចូលគណនីតាម Google ជោគជ័យ!' : 'Signed in with Google!');
    return;
  }

  if (oauthError) {
    error.value = locale.value === 'km'
      ? 'ការភ្ជាប់ Google មិនជោគជ័យ សូមសាកល្បងម្តងទៀត។'
      : `Google sign-in failed (${oauthError}). Please try again.`;
    await router.replace('/account');
  }

  auth.hydrate();
  loadAccount();
});

async function handleGoogleAuth() {
  googleLoading.value = true;
  error.value = null;
  try {
    const res = await request<{ mode: 'live' | 'dev_simulator'; authorization_url: string | null }>('/auth/google/redirect');
    if (res.mode === 'live' && res.authorization_url && import.meta.client) {
      window.location.href = res.authorization_url;
      return;
    }

    // Local dev simulator when GOOGLE_CLIENT_ID is not yet configured in .env
    const devRes = await request<{ token: string; user: AuthUser }>('/auth/google/dev-login', {
      method: 'POST',
      body: {},
    });
    auth.setSession(devRes.token, devRes.user);
    await loadAccount();
    toast.success(locale.value === 'km' ? 'ចូលគណនីតាម Google (Dev Mode) ជោគជ័យ!' : 'Signed in with Google (Dev Mode)!');
  } catch (err: any) {
    error.value = err?.data?.message || (locale.value === 'km' ? 'មិនអាចភ្ជាប់ Google បានទេ។' : 'Unable to connect to Google Sign-In.');
  } finally {
    googleLoading.value = false;
  }
}

function fillDemoCustomer() {
  mode.value = 'login';
  email.value = 'player@khmertopup.local';
  password.value = 'CustomerPass123!';
  toast.info(locale.value === 'km' ? 'បានបំពេញគណនីសាកល្បងរួចរាល់' : 'Demo customer credentials filled');
}

async function submitAuth() {
  error.value = null;
  submitting.value = true;
  try {
    if (mode.value === 'login') {
      const res = await request<{ token: string; user: AuthUser }>('/auth/login', {
        method: 'POST',
        body: {
          email: email.value,
          password: password.value,
          mfa_code: loginMfaCode.value || undefined,
        },
      });
      auth.setSession(res.token, res.user);
      loginMfaRequired.value = false;
      loginMfaCode.value = '';
      toast.success(locale.value === 'km' ? 'ចូលគណនីជោគជ័យ!' : 'Signed in successfully!');
    } else {
      const res = await request<{ token: string; user: AuthUser }>('/auth/register', {
        method: 'POST',
        body: {
          name: name.value,
          email: email.value,
          phone: phone.value || null,
          password: password.value,
          password_confirmation: passwordConfirmation.value,
        },
      });
      auth.setSession(res.token, res.user);
      toast.success(locale.value === 'km' ? 'បង្កើតគណនីថ្មីជោគជ័យ!' : 'Account created!');
    }
    await loadAccount();
  } catch (err: any) {
    if (err?.data?.mfa_required) {
      loginMfaRequired.value = true;
      error.value = locale.value === 'km' ? 'សូមបញ្ចូលកូដ 2FA 6 ខ្ទង់ពីកម្មវិធី Authenticator។' : 'Enter your 6-digit 2FA authenticator code.';
    } else {
      error.value = err?.data?.message || (locale.value === 'km' ? 'ការចូលគណនីមិនជោគជ័យ សូមពិនិត្យម្តងទៀត។' : 'Authentication failed. Please check your credentials.');
    }
  } finally {
    submitting.value = false;
  }
}

async function saveProfile() {
  savingProfile.value = true;
  try {
    const res = await request<{ user: AuthUser }>('/auth/profile', {
      method: 'PATCH',
      body: {
        name: profileName.value.trim(),
        phone: profilePhone.value.trim() || null,
        preferred_locale: profileLocale.value,
        preferred_currency: profileCurrency.value,
        notify_email: notifyEmail.value,
        notify_in_app: notifyInApp.value,
        notify_telegram: notifyTelegram.value,
      },
    });
    auth.user = res.user;
    setLocale(profileLocale.value);
    prefs.setCurrency(profileCurrency.value);
    toast.success(locale.value === 'km' ? 'បានរក្សាទុកព័ត៌មានគណនីជោគជ័យ!' : 'Profile updated successfully!');
  } catch (err: any) {
    toast.error(err?.data?.message || 'Unable to save profile.');
  } finally {
    savingProfile.value = false;
  }
}

async function startMfaSetup() {
  settingUpMfa.value = true;
  try {
    const res = await request<{ secret: string; otpauth_uri: string }>('/auth/mfa/setup', { method: 'POST' });
    mfaSecret.value = res.secret;
  } catch (err: any) {
    toast.error(err?.data?.message || 'Unable to start MFA setup.');
  } finally {
    settingUpMfa.value = false;
  }
}

async function confirmMfa() {
  if (mfaCode.value.length !== 6) return;
  settingUpMfa.value = true;
  try {
    const res = await request<{ user: AuthUser }>('/auth/mfa/confirm', {
      method: 'POST',
      body: { code: mfaCode.value },
    });
    auth.user = res.user;
    mfaSecret.value = null;
    mfaCode.value = '';
    toast.success(locale.value === 'km' ? 'បានបើកសុវត្ថិភាព 2FA MFA ជោគជ័យ!' : '2FA MFA enabled!');
  } catch (err: any) {
    toast.error(err?.data?.message || 'Invalid 6-digit MFA code.');
  } finally {
    settingUpMfa.value = false;
  }
}

async function copyMfaSecret() {
  if (!mfaSecret.value || !import.meta.client) return;
  await navigator.clipboard.writeText(mfaSecret.value);
  copiedSecret.value = true;
  toast.info('MFA Secret copied');
  setTimeout(() => { copiedSecret.value = false; }, 2000);
}

async function logout() {
  try {
    await request('/auth/logout', { method: 'POST' });
  } finally {
    auth.clearSession();
    orders.value = [];
    tickets.value = [];
    toast.info(locale.value === 'km' ? 'បានចាកចេញពីគណនី' : 'Signed out');
  }
}
</script>

<template>
  <div class="mx-auto max-w-5xl px-3.5 sm:px-6 pt-5 pb-28 sm:py-12">
    <!-- Unauthenticated: Ultra-Modern Mobile-First Split Esports/Fintech Auth Card -->
    <div
      v-if="!auth.isAuthenticated"
      class="animate-fade-up relative grid grid-cols-1 lg:grid-cols-12 rounded-[24px] sm:rounded-[28px] overflow-hidden border border-white/15 bg-[#0A1124]/95 shadow-[0_28px_80px_-20px_rgba(0,0,0,0.8)]"
    >
      <!-- Subtle Top Accent Glow Line -->
      <div class="absolute inset-x-0 top-0 h-[2px] bg-gradient-to-r from-transparent via-sky-400/80 to-transparent z-20 pointer-events-none" />

      <!-- Left Brand & Gaming Showcase Panel (Compact Header on Mobile, Full Showcase on Desktop) -->
      <div class="lg:col-span-5 relative overflow-hidden p-5 sm:p-7 lg:p-9 flex flex-col justify-between lg:min-h-[600px] border-b lg:border-b-0 lg:border-r border-white/10">
        <img
          src="/images/games/mobile-legends.jpg"
          alt="VannTopUp Gaming"
          class="absolute inset-0 h-full w-full object-cover opacity-35 lg:opacity-45 scale-105"
        />
        <div class="absolute inset-0 bg-gradient-to-br from-[#0B1736]/94 via-[#0A1228]/90 to-sky-900/50" />
        <div class="absolute -bottom-24 -left-16 w-72 h-72 rounded-full bg-blue-500/25 blur-[90px] pointer-events-none" />

        <div class="relative z-10 space-y-3.5 lg:space-y-5">
          <!-- Top Row: Back to Home Pill + Security Badge -->
          <div class="flex items-center justify-between gap-2 flex-wrap">
            <NuxtLink
              to="/"
              class="inline-flex items-center gap-1.5 rounded-full border border-white/15 bg-white/[0.06] hover:bg-white/[0.12] px-3 py-1.5 text-[11px] font-semibold text-slate-200 hover:text-white backdrop-blur-md transition-colors"
            >
              <ArrowLeft class="h-3.5 w-3.5" />
              <span>{{ locale === 'km' ? 'ត្រឡប់ទៅទំព័រដើម' : 'Back to home' }}</span>
            </NuxtLink>

            <span class="inline-flex items-center gap-1.5 rounded-full border border-sky-400/30 bg-sky-500/15 px-2.5 py-1 text-[10px] font-bold uppercase tracking-wider text-sky-300">
              <ShieldCheck class="h-3 w-3 shrink-0" />
              <span>{{ mode === 'login' ? (locale === 'km' ? 'ចូលដោយសុវត្ថិភាព' : 'SECURE SIGN IN') : (locale === 'km' ? 'ចុះឈ្មោះឥតគិតថ្លៃ' : 'FREE TO JOIN') }}</span>
            </span>
          </div>

          <!-- Brand Logo + Name -->
          <div class="flex items-center gap-3 pt-0.5">
            <img
              src="/images/brand/vanntopup-logo.jpg"
              alt="VannTopUp Logo"
              class="h-10 w-10 sm:h-11 sm:w-11 rounded-2xl object-cover border border-sky-400/50 shadow-lg shadow-blue-600/35 shrink-0"
            />
            <div>
              <p class="text-base sm:text-lg font-bold tracking-tight text-white leading-none">
                Vann<span class="text-sky-400">TopUp</span>
              </p>
              <p class="text-[10px] font-semibold uppercase tracking-widest text-sky-300/80 mt-1">Cambodia Member Portal</p>
            </div>
          </div>

          <!-- Headline (Compact on Mobile, Full Subtitle on Desktop) -->
          <div class="space-y-2 lg:pt-2">
            <h1 class="text-lg sm:text-2xl lg:text-[27px] font-bold text-white leading-snug lg:leading-[1.35]">
              {{ mode === 'login'
                ? (locale === 'km' ? 'សូមស្វាគមន៍មកកាន់ VannTopUp' : 'Welcome back to VannTopUp.')
                : (locale === 'km' ? 'បង្កើតគណនីក្នុងរយៈពេលប៉ុន្មានវិនាទី' : 'Create your account in seconds.') }}
            </h1>
            <p class="hidden lg:block text-sm text-slate-300/90 leading-relaxed">
              {{ mode === 'login'
                ? (locale === 'km'
                  ? 'ចូលគណនីដើម្បីមើលប្រវត្តិការបញ្ជាទិញទាំងអស់ តាមដានស្ថានភាពភ្លាមៗ និងទទួលបានប្រូម៉ូសិនពិសេស។'
                  : 'Sign in to track all your orders automatically, unlock VIP promo codes, and speed through KHQR checkout.')
                : (locale === 'km'
                  ? 'ចុះឈ្មោះដើម្បីបញ្ចូលហ្គេមស្វ័យប្រវត្តិលឿនទាន់ចិត្ត រក្សាទុកវិក្កយបត្រ និងទទួលជំនួយ ២៤/៧។'
                  : 'Sign up for fast checkout, automated top-ups, and instant order delivery.') }}
            </p>
          </div>
        </div>

        <!-- Bottom Feature List + 4 Official Game Logos Stack (Desktop Only for Clean Mobile Above-the-Fold) -->
        <div class="hidden lg:block relative z-10 space-y-5 pt-8">
          <div class="space-y-2.5">
            <div class="flex items-center gap-3 text-xs text-slate-100 rounded-xl bg-white/[0.04] border border-white/10 px-3.5 py-2.5 backdrop-blur-md">
              <History class="h-4 w-4 text-sky-400 shrink-0" />
              <span>{{ locale === 'km' ? 'រក្សាទុកប្រវត្តិការបញ្ជាទិញដោយស្វ័យប្រវត្តិ' : 'Automatic full order history & receipts' }}</span>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-100 rounded-xl bg-white/[0.04] border border-white/10 px-3.5 py-2.5 backdrop-blur-md">
              <ShieldCheck class="h-4 w-4 text-emerald-400 shrink-0" />
              <span>{{ locale === 'km' ? 'សុវត្ថិភាពខ្ពស់ជាមួយ 2FA MFA & Encryption' : 'Bank-grade encryption & 2FA MFA security' }}</span>
            </div>
            <div class="flex items-center gap-3 text-xs text-slate-100 rounded-xl bg-white/[0.04] border border-white/10 px-3.5 py-2.5 backdrop-blur-md">
              <Zap class="h-4 w-4 text-amber-400 shrink-0" />
              <span>{{ locale === 'km' ? 'បញ្ចូលភ្លាមៗ ២៤/៧ តាម Bakong KHQR' : 'Instant 24/7 delivery via Bakong KHQR' }}</span>
            </div>
          </div>

          <div class="flex items-center gap-3 pt-1">
            <div class="flex -space-x-2">
              <img
                v-for="g in quickGames"
                :key="g.slug"
                :src="g.logo"
                :alt="g.name"
                class="h-8 w-8 rounded-lg object-cover border-2 border-[#0B142C] shadow"
              />
            </div>
            <span class="text-[11px] font-medium text-slate-300">
              {{ locale === 'km' ? 'គាំទ្រហ្គេមពេញនិយមនៅកម្ពុជា' : 'Official Cambodia Game Catalog' }}
            </span>
          </div>
        </div>
      </div>

      <!-- Right Auth Form Panel -->
      <div class="lg:col-span-7 p-5 sm:p-8 lg:p-11 flex flex-col justify-center space-y-5 bg-gradient-to-b from-[#0A1124] to-[#070C1A]">
        <!-- Smooth Animated Sliding Segmented Control -->
        <div class="relative grid grid-cols-2 rounded-2xl bg-[#060A15] p-1.5 border border-white/10">
          <div
            class="absolute top-1.5 bottom-1.5 w-[calc(50%-6px)] rounded-xl btn-primary-glow transition-transform duration-300 ease-out pointer-events-none"
            :class="mode === 'login' ? 'translate-x-0' : 'translate-x-full'"
          />
          <button
            type="button"
            class="relative z-10 min-h-[40px] py-2 rounded-xl text-xs sm:text-sm font-bold transition-colors"
            :class="mode === 'login' ? 'text-white' : 'text-slate-400 hover:text-white'"
            @click="mode = 'login'; error = null; loginMfaRequired = false"
          >
            {{ locale === 'km' ? 'ចូលគណនី' : 'Sign In' }}
          </button>
          <button
            type="button"
            class="relative z-10 min-h-[40px] py-2 rounded-xl text-xs sm:text-sm font-bold transition-colors"
            :class="mode === 'register' ? 'text-white' : 'text-slate-400 hover:text-white'"
            @click="mode = 'register'; error = null; loginMfaRequired = false"
          >
            {{ locale === 'km' ? 'បង្កើតគណនីថ្មី' : 'Create Account' }}
          </button>
        </div>

        <!-- Contextual Eyebrow & Welcome Header -->
        <div class="space-y-1">
          <p class="text-[11px] font-bold uppercase tracking-widest text-sky-400">
            {{ mode === 'login' ? (locale === 'km' ? 'ចូលគណនី' : 'SIGN IN') : (locale === 'km' ? 'ចាប់ផ្ដើមឥឡូវនេះ' : 'GET STARTED') }}
          </p>
          <h2 class="text-xl sm:text-2xl font-bold text-white leading-snug">
            {{ mode === 'login'
              ? (locale === 'km' ? 'សួស្តីម្តងទៀត!' : 'Hello again.')
              : (locale === 'km' ? 'បង្កើតគណនី VannTopUp' : 'Create account.') }}
          </h2>
          <p class="text-xs text-slate-400">
            {{ mode === 'login'
              ? (locale === 'km' ? 'បន្តជាមួយ Google ឬបញ្ចូលព័ត៌មានខាងក្រោម។' : 'Continue with Google or enter your details below.')
              : (locale === 'km' ? 'ចុះឈ្មោះតាម Google ឬបំពេញព័ត៌មានខាងក្រោម។' : 'Sign up with Google or enter your details below.') }}
          </p>
        </div>

        <Transition name="pop">
          <div v-if="error" class="rounded-xl bg-red-500/15 border border-red-500/40 px-4 py-3 text-xs font-medium text-red-200">
            {{ error }}
          </div>
        </Transition>

        <!-- Official-Style Continue with Google Button (46px Touch Target) -->
        <button
          type="button"
          :disabled="googleLoading"
          class="w-full min-h-[46px] rounded-2xl border border-white/15 bg-white hover:bg-slate-100 text-slate-900 py-3 px-4 text-xs sm:text-sm font-bold inline-flex items-center justify-center gap-3 shadow-lg transition-all active:scale-[0.98] disabled:opacity-60"
          @click="handleGoogleAuth"
        >
          <Loader2 v-if="googleLoading" class="h-4 w-4 animate-spin text-slate-700" />
          <svg v-else class="h-5 w-5 shrink-0" viewBox="0 0 24 24" aria-hidden="true">
            <path fill="#4285F4" d="M23.745 12.27c0-.7-.06-1.4-.19-2.07H12v4.51h6.6c-.29 1.52-1.14 2.82-2.4 3.68v3.05h3.88c2.27-2.09 3.665-5.17 3.665-9.17z" />
            <path fill="#34A853" d="M12 24c3.24 0 5.95-1.08 7.93-2.91l-3.88-3.05c-1.08.72-2.45 1.16-4.05 1.16-3.12 0-5.77-2.11-6.72-4.96H1.29v3.14C3.26 21.3 7.31 24 12 24z" />
            <path fill="#FBBC05" d="M5.28 14.24c-.24-.72-.38-1.49-.38-2.24s.14-1.52.38-2.24V6.62H1.29C.47 8.24 0 10.06 0 12s.47 3.76 1.29 5.38l3.99-3.14z" />
            <path fill="#EA4335" d="M12 4.75c1.77 0 3.35.61 4.6 1.8l3.42-3.42C17.95 1.19 15.24 0 12 0 7.31 0 3.26 2.7 1.29 6.62l3.99 3.14c.95-2.85 3.6-4.96 6.72-4.96z" />
          </svg>
          <span>{{ mode === 'login' ? (locale === 'km' ? 'បន្តជាមួយ Google' : 'Continue with Google') : (locale === 'km' ? 'ចុះឈ្មោះជាមួយ Google' : 'Sign up with Google') }}</span>
        </button>

        <!-- Clean Divider -->
        <div class="relative flex items-center">
          <div class="flex-grow border-t border-white/10" />
          <span class="px-3 text-[11px] font-semibold uppercase tracking-wider text-slate-500">
            {{ mode === 'login' ? (locale === 'km' ? 'ឬប្រើអ៊ីមែល' : 'OR') : (locale === 'km' ? 'ឬចុះឈ្មោះតាមអ៊ីមែល' : 'OR SIGN UP WITH EMAIL') }}
          </span>
          <div class="flex-grow border-t border-white/10" />
        </div>

        <form class="space-y-4" @submit.prevent="submitAuth">
          <!-- Register: Display Name & Optional Phone -->
          <div v-if="mode === 'register'" class="grid grid-cols-1 sm:grid-cols-2 gap-3.5">
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'ឈ្មោះបង្ហាញ' : 'Display Name' }}</label>
              <div class="group relative">
                <User class="h-4 w-4 text-slate-500 group-focus-within:text-sky-400 transition-colors absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  v-model="name"
                  type="text"
                  autocomplete="name"
                  required
                  :placeholder="locale === 'km' ? 'ឧ. សុខា ហ្គេមមឺ' : 'Your store or gamer name'"
                  class="w-full min-h-[46px] rounded-xl border border-white/15 bg-[#060A15] pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 focus:outline-none transition-all"
                />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'លេខទូរស័ព្ទ (ជាជម្រើស)' : 'Phone (Optional)' }}</label>
              <div class="group relative">
                <Phone class="h-4 w-4 text-slate-500 group-focus-within:text-sky-400 transition-colors absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  v-model="phone"
                  type="tel"
                  autocomplete="tel"
                  placeholder="+855 12 345 678"
                  class="w-full min-h-[46px] rounded-xl border border-white/15 bg-[#060A15] pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 focus:outline-none transition-all"
                />
              </div>
            </div>
          </div>

          <!-- Email Address -->
          <div class="space-y-1.5">
            <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'អាសយដ្ឋានអ៊ីមែល' : 'Email Address' }}</label>
            <div class="group relative">
              <Mail class="h-4 w-4 text-slate-500 group-focus-within:text-sky-400 transition-colors absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
              <input
                v-model="email"
                type="email"
                autocomplete="email"
                required
                placeholder="you@example.com"
                class="w-full min-h-[46px] rounded-xl border border-white/15 bg-[#060A15] pl-10 pr-4 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 focus:outline-none transition-all"
              />
            </div>
          </div>

          <!-- Password (+ Side-by-Side Confirm Password on Register, Stacked on Mobile) -->
          <div :class="mode === 'register' ? 'grid grid-cols-1 sm:grid-cols-2 gap-3.5' : 'space-y-1.5'">
            <div class="space-y-1.5">
              <div class="flex items-center justify-between gap-2">
                <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'ពាក្យសម្ងាត់' : 'Password' }}</label>
                <NuxtLink v-if="mode === 'login'" to="/contact" class="text-xs font-semibold text-sky-400 hover:text-sky-300 transition-colors">
                  {{ locale === 'km' ? 'ភ្លេចពាក្យសម្ងាត់?' : 'Forgot?' }}
                </NuxtLink>
              </div>
              <div class="group relative">
                <Lock class="h-4 w-4 text-slate-500 group-focus-within:text-sky-400 transition-colors absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  v-model="password"
                  :type="showPassword ? 'text' : 'password'"
                  :autocomplete="mode === 'login' ? 'current-password' : 'new-password'"
                  required
                  :placeholder="locale === 'km' ? 'យ៉ាងតិច ១០ តួអក្សរ' : 'Min 10 characters'"
                  class="w-full min-h-[46px] rounded-xl border border-white/15 bg-[#060A15] pl-10 pr-12 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 focus:outline-none transition-all"
                />
                <button
                  type="button"
                  :aria-label="showPassword ? 'Hide password' : 'Show password'"
                  class="absolute right-0.5 inset-y-0 w-11 flex items-center justify-center rounded-r-xl text-slate-400 hover:text-white transition-colors"
                  @click="showPassword = !showPassword"
                >
                  <EyeOff v-if="showPassword" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>
            </div>

            <div v-if="mode === 'register'" class="space-y-1.5">
              <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'បញ្ជាក់ពាក្យសម្ងាត់' : 'Confirm Password' }}</label>
              <div class="group relative">
                <Lock class="h-4 w-4 text-slate-500 group-focus-within:text-sky-400 transition-colors absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input
                  v-model="passwordConfirmation"
                  :type="showConfirmPassword ? 'text' : 'password'"
                  autocomplete="new-password"
                  required
                  :placeholder="locale === 'km' ? 'វាយម្តងទៀត' : 'Re-enter password'"
                  class="w-full min-h-[46px] rounded-xl border border-white/15 bg-[#060A15] pl-10 pr-12 py-3 text-sm text-white placeholder-slate-500 focus:border-sky-400 focus:ring-2 focus:ring-sky-400/20 focus:outline-none transition-all"
                />
                <button
                  type="button"
                  :aria-label="showConfirmPassword ? 'Hide confirm password' : 'Show confirm password'"
                  class="absolute right-0.5 inset-y-0 w-11 flex items-center justify-center rounded-r-xl text-slate-400 hover:text-white transition-colors"
                  @click="showConfirmPassword = !showConfirmPassword"
                >
                  <EyeOff v-if="showConfirmPassword" class="h-4 w-4" />
                  <Eye v-else class="h-4 w-4" />
                </button>
              </div>
            </div>
          </div>

          <!-- Live Password Strength Meter on Register -->
          <div v-if="mode === 'register' && password.length > 0" class="grid grid-cols-4 gap-1.5">
            <div
              v-for="bar in 4"
              :key="bar"
              class="h-1.5 rounded-full transition-colors duration-200"
              :class="passwordStrength >= bar
                ? (passwordStrength <= 1 ? 'bg-red-500' : passwordStrength === 2 ? 'bg-amber-400' : passwordStrength === 3 ? 'bg-sky-400' : 'bg-emerald-400')
                : 'bg-white/10'"
            />
          </div>

          <!-- Trust Device Checkbox on Login -->
          <label v-if="mode === 'login'" class="flex items-center gap-2.5 text-xs text-slate-300 cursor-pointer select-none py-0.5">
            <input
              v-model="trustDevice"
              type="checkbox"
              class="h-4 w-4 rounded border-white/20 bg-[#060A15] text-sky-500 focus:ring-sky-400/30"
            />
            <span>{{ locale === 'km' ? 'ចងចាំឧបករណ៍នេះរយៈពេល ៣០ ថ្ងៃ' : 'Trust this device for 30 days' }}</span>
          </label>

          <Transition name="drawer">
            <div v-if="loginMfaRequired && mode === 'login'" class="space-y-1.5">
              <label class="block text-xs font-medium text-sky-300">{{ locale === 'km' ? 'កូដ 2FA 6 ខ្ទង់ (Authenticator)' : '6-Digit 2FA Code' }}</label>
              <div class="group relative">
                <KeyRound class="h-4 w-4 text-sky-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                <input v-model="loginMfaCode" type="text" maxlength="6" inputmode="numeric" required placeholder="123456" class="w-full min-h-[46px] rounded-xl border border-sky-400/50 bg-[#060A15] pl-10 pr-4 py-3 font-mono text-sm text-white focus:border-sky-400 focus:outline-none" />
              </div>
            </div>
          </Transition>

          <button type="submit" :disabled="submitting" class="w-full min-h-[48px] btn-primary-glow disabled:opacity-50 rounded-2xl py-3.5 text-sm font-bold text-white inline-flex items-center justify-center gap-2 mt-1">
            <Loader2 v-if="submitting" class="h-4 w-4 animate-spin" />
            <span>{{ mode === 'login' ? (locale === 'km' ? 'ចូលគណនី' : 'Sign In') : (locale === 'km' ? 'បង្កើតគណនីឥឡូវនេះ' : 'Create Account') }}</span>
            <ArrowRight v-if="!submitting" class="h-4 w-4" />
          </button>
        </form>

        <!-- Clean Footer: Switch Mode + Demo & Guest Options -->
        <div class="pt-4 border-t border-white/10 space-y-3 text-xs">
          <p class="text-center text-slate-400">
            <template v-if="mode === 'login'">
              {{ locale === 'km' ? 'មិនទាន់មានគណនី VannTopUp មែនទេ?' : 'New to VannTopUp?' }}
              <button type="button" class="font-bold text-sky-400 hover:text-sky-300 ml-1" @click="mode = 'register'; error = null">
                {{ locale === 'km' ? 'បង្កើតគណនីថ្មី' : 'Create an account' }}
              </button>
            </template>
            <template v-else>
              {{ locale === 'km' ? 'មានគណនីរួចហើយមែនទេ?' : 'Already have an account?' }}
              <button type="button" class="font-bold text-sky-400 hover:text-sky-300 ml-1" @click="mode = 'login'; error = null">
                {{ locale === 'km' ? 'ចូលគណនី' : 'Sign in' }}
              </button>
            </template>
          </p>

          <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-2.5 pt-1">
            <button
              type="button"
              class="inline-flex items-center justify-center gap-1.5 rounded-xl border border-sky-400/30 bg-sky-500/10 hover:bg-sky-500/20 px-3.5 py-2.5 font-semibold text-sky-300 transition-colors"
              @click="fillDemoCustomer"
            >
              <Sparkles class="h-3.5 w-3.5 text-amber-400" />
              <span>{{ locale === 'km' ? 'ប្រើគណនីសាកល្បង (Demo)' : 'Fill Demo Account' }}</span>
            </button>

            <NuxtLink to="/games" class="text-center text-slate-400 hover:text-white font-medium py-1.5 transition-colors">
              {{ locale === 'km' ? 'ឬបន្តទិញជាភ្ញៀវ (Guest) →' : 'Or continue as guest →' }}
            </NuxtLink>
          </div>
        </div>
      </div>
    </div>

    <!-- Authenticated Gamer Profile Portal -->
    <div v-else class="animate-fade-up space-y-6">
      <!-- Rich Profile Hero Banner -->
      <div class="relative overflow-hidden rounded-3xl border border-white/15 bg-gradient-to-r from-[#111D3B] via-[#0B1226] to-[#0E1930] p-5 sm:p-7 shadow-2xl">
        <div class="absolute -top-20 right-10 w-64 h-64 rounded-full bg-sky-500/15 blur-[80px] pointer-events-none" />

        <div class="relative z-10 flex flex-col sm:flex-row sm:items-center justify-between gap-5">
          <div class="flex items-center gap-4">
            <div class="relative h-16 w-16 sm:h-18 sm:w-18 rounded-2xl bg-gradient-to-br from-blue-600 to-sky-400 flex items-center justify-center text-2xl font-bold text-white shadow-xl shadow-blue-600/30 ring-2 ring-sky-400/40 shrink-0 overflow-hidden">
              <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" :alt="auth.user.name" class="h-full w-full object-cover" />
              <span v-else>{{ auth.user?.name?.charAt(0)?.toUpperCase() || 'U' }}</span>
              <span class="absolute -bottom-1 -right-1 h-5 w-5 rounded-full bg-emerald-500 border-2 border-[#0B1226] flex items-center justify-center">
                <Check class="h-3 w-3 text-white stroke-[3]" />
              </span>
            </div>
            <div class="min-w-0">
              <div class="flex flex-wrap items-center gap-2">
                <h1 class="text-xl sm:text-2xl font-bold text-white truncate">{{ auth.user?.name }}</h1>
                <span class="rounded-full bg-emerald-500/15 border border-emerald-400/30 px-2.5 py-0.5 text-[10px] font-bold text-emerald-300">
                  {{ locale === 'km' ? 'សមាជិកផ្លូវការ' : 'Verified Member' }}
                </span>
              </div>
              <p class="text-xs text-slate-300 mt-0.5 truncate">{{ auth.user?.email }}</p>
              <p v-if="auth.user?.phone" class="text-[11px] text-slate-400 mt-0.5">{{ auth.user.phone }}</p>
            </div>
          </div>

          <button
            type="button"
            class="self-start sm:self-center inline-flex items-center gap-2 rounded-xl border border-red-500/35 bg-red-500/10 hover:bg-red-500/20 px-4 py-2.5 text-xs font-bold text-red-300 active:scale-95 transition-all"
            @click="logout"
          >
            <LogOut class="h-4 w-4" />
            <span>{{ locale === 'km' ? 'ចាកចេញ' : 'Sign Out' }}</span>
          </button>
        </div>

        <!-- 3 Live Account Stat Cards -->
        <div class="relative z-10 grid grid-cols-3 gap-2.5 sm:gap-4 mt-6 pt-5 border-t border-white/10">
          <div class="rounded-2xl bg-[#070B16]/75 border border-white/10 p-3.5 sm:p-4">
            <p class="text-[10px] sm:text-xs text-slate-400">{{ locale === 'km' ? 'ការបញ្ជាទិញសរុប' : 'Total Orders' }}</p>
            <p class="text-xl sm:text-2xl font-bold text-white tabular-nums mt-0.5">{{ orders.length }}</p>
          </div>
          <div class="rounded-2xl bg-[#070B16]/75 border border-white/10 p-3.5 sm:p-4">
            <p class="text-[10px] sm:text-xs text-slate-400">{{ locale === 'km' ? 'បញ្ចូលជោគជ័យ' : 'Delivered' }}</p>
            <p class="text-xl sm:text-2xl font-bold text-emerald-400 tabular-nums mt-0.5">{{ completedOrdersCount }}</p>
          </div>
          <div class="rounded-2xl bg-[#070B16]/75 border border-white/10 p-3.5 sm:p-4">
            <p class="text-[10px] sm:text-xs text-slate-400">{{ locale === 'km' ? 'សុវត្ថិភាព 2FA' : '2FA Security' }}</p>
            <p class="text-xs sm:text-sm font-bold mt-1.5" :class="auth.user?.mfa_enabled ? 'text-emerald-400' : 'text-amber-300'">
              {{ auth.user?.mfa_enabled ? (locale === 'km' ? 'បានបើក' : 'Enabled') : (locale === 'km' ? 'មិនទាន់បើក' : 'Off') }}
            </p>
          </div>
        </div>
      </div>

      <!-- Tabbed Navigation Bar -->
      <div class="flex items-center gap-2 overflow-x-auto pb-1">
        <button
          v-for="tab in [
            { id: 'orders', label: locale === 'km' ? 'ប្រវត្តិការបញ្ជាទិញ' : 'Order History', icon: History },
            { id: 'profile', label: locale === 'km' ? 'ព័ត៌មានផ្ទាល់ខ្លួន' : 'Profile & Settings', icon: Settings },
            { id: 'tickets', label: locale === 'km' ? 'សំបុត្រជំនួយ' : 'Support Tickets', icon: Headphones },
            { id: 'security', label: locale === 'km' ? 'សុវត្ថិភាព 2FA' : 'Security & 2FA', icon: ShieldCheck },
          ]"
          :key="tab.id"
          type="button"
          class="inline-flex items-center gap-2 rounded-xl px-4 py-2.5 text-xs font-bold whitespace-nowrap transition-all active:scale-95 shrink-0"
          :class="activeTab === tab.id
            ? 'btn-primary-glow text-white'
            : 'border border-white/10 bg-[#0B1224] text-slate-300 hover:text-white hover:border-white/25'"
          @click="activeTab = tab.id as any"
        >
          <component :is="tab.icon" class="h-4 w-4" />
          <span>{{ tab.label }}</span>
        </button>
      </div>

      <!-- TAB 1: ORDER HISTORY -->
      <div v-if="activeTab === 'orders'" class="glass-card rounded-3xl p-5 sm:p-7 space-y-5">
        <div class="flex items-center justify-between">
          <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <History class="h-5 w-5 text-sky-400" />
            <span>{{ locale === 'km' ? 'ប្រវត្តិការបញ្ជាទិញរបស់អ្នក' : 'Your Order History' }}</span>
          </h2>
          <NuxtLink to="/games" class="text-xs font-bold text-sky-400 hover:underline">
            {{ locale === 'km' ? '+ បញ្ចូលហ្គេមថ្មី' : '+ New Top-Up' }}
          </NuxtLink>
        </div>

        <!-- Empty State with Quick Game Launchers -->
        <div v-if="orders.length === 0" class="rounded-2xl border border-dashed border-white/15 bg-[#070B16]/50 p-6 sm:p-8 text-center space-y-5">
          <div class="space-y-1">
            <p class="text-sm font-bold text-white">{{ locale === 'km' ? 'អ្នកមិនទាន់មានការបញ្ជាទិញនៅឡើយទេ' : 'No orders placed yet' }}</p>
            <p class="text-xs text-slate-400">{{ locale === 'km' ? 'ជ្រើសរើសហ្គេមខាងក្រោមដើម្បីចាប់ផ្តើមបញ្ចូលទឹកប្រាក់ភ្លាមៗ៖' : 'Pick a game below to start your first instant top-up:' }}</p>
          </div>
          <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 max-w-2xl mx-auto">
            <NuxtLink
              v-for="g in quickGames"
              :key="g.slug"
              :to="`/games/${g.slug}`"
              class="rounded-2xl border border-white/10 bg-[#0D1528] hover:border-sky-400/50 p-3 flex flex-col items-center gap-2 transition-all hover:-translate-y-0.5"
            >
              <img :src="g.logo" :alt="g.name" class="h-12 w-12 rounded-xl object-cover border border-white/15 shadow" />
              <span class="text-xs font-bold text-white truncate max-w-full">{{ g.name }}</span>
            </NuxtLink>
          </div>
        </div>

        <div v-else class="space-y-3">
          <div
            v-for="o in orders"
            :key="o.id"
            class="rounded-2xl border border-white/10 bg-[#070B16]/85 hover:border-sky-400/40 p-4 flex flex-wrap items-center justify-between gap-4 transition-colors"
          >
            <div class="flex items-center gap-3.5 min-w-0">
              <img
                :src="logoBySlug[o.game_slug] || '/images/games/icons/mobile-legends-logo.jpg'"
                :alt="o.game_name"
                class="h-11 w-11 rounded-xl object-cover border border-white/15 shrink-0"
              />
              <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                  <p class="font-mono text-xs sm:text-sm font-bold text-white">{{ o.order_number }}</p>
                  <span class="inline-flex items-center gap-1 rounded-full bg-emerald-500/15 px-2 py-0.5 text-[10px] font-bold uppercase text-emerald-300">
                    <CheckCircle2 class="h-3 w-3" /> {{ o.status }}
                  </span>
                </div>
                <p class="text-xs text-slate-400 truncate mt-0.5">{{ o.game_name }} — {{ o.package_name_en }}</p>
              </div>
            </div>
            <div class="flex items-center gap-3 sm:gap-4">
              <p class="text-sm sm:text-base font-bold text-sky-300 tabular-nums">{{ formatMoney(o.total_minor, o.currency) }}</p>
              <NuxtLink
                :to="`/track/${o.order_number}`"
                class="rounded-xl border border-sky-400/40 bg-sky-500/10 hover:bg-sky-500/20 px-3.5 py-2 text-xs font-bold text-sky-300 transition-colors"
              >
                {{ locale === 'km' ? 'ពិនិត្យ' : 'Track' }}
              </NuxtLink>
            </div>
          </div>
        </div>
      </div>

      <!-- TAB 2: PROFILE & PREFERENCES -->
      <div v-else-if="activeTab === 'profile'" class="glass-card rounded-3xl p-5 sm:p-7 space-y-6">
        <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
          <Settings class="h-5 w-5 text-sky-400" />
          <span>{{ locale === 'km' ? 'កែប្រែព័ត៌មានផ្ទាល់ខ្លួន និងការជូនដំណឹង' : 'Edit Profile & Preferences' }}</span>
        </h2>

        <form class="space-y-5" @submit.prevent="saveProfile">
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'ឈ្មោះពេញ' : 'Full Name' }}</label>
              <div class="relative">
                <User class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input v-model="profileName" type="text" required class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-10 pr-4 py-3 text-sm text-white focus:border-sky-400 focus:outline-none" />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'លេខទូរស័ព្ទ' : 'Phone Number' }}</label>
              <div class="relative">
                <Phone class="h-4 w-4 text-slate-400 absolute left-3.5 top-1/2 -translate-y-1/2" />
                <input v-model="profilePhone" type="tel" placeholder="+855 12 345 678" class="w-full rounded-xl border border-white/15 bg-[#070B16] pl-10 pr-4 py-3 text-sm text-white focus:border-sky-400 focus:outline-none" />
              </div>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'ភាសាគោល' : 'Preferred Language' }}</label>
              <select v-model="profileLocale" class="w-full rounded-xl border border-white/15 bg-[#070B16] px-4 py-3 text-sm text-white focus:border-sky-400 focus:outline-none">
                <option value="km">ភាសាខ្មែរ (Khmer)</option>
                <option value="en">English</option>
              </select>
            </div>

            <div class="space-y-1.5">
              <label class="block text-xs font-medium text-slate-300">{{ locale === 'km' ? 'រូបិយប័ណ្ណគោល' : 'Preferred Currency' }}</label>
              <select v-model="profileCurrency" class="w-full rounded-xl border border-white/15 bg-[#070B16] px-4 py-3 text-sm text-white focus:border-sky-400 focus:outline-none">
                <option value="USD">$ USD (US Dollar)</option>
                <option value="KHR">៛ KHR (Khmer Riel)</option>
              </select>
            </div>
          </div>

          <div class="pt-3 border-t border-white/10 space-y-3">
            <p class="text-xs font-bold text-slate-300 flex items-center gap-1.5">
              <Bell class="h-4 w-4 text-sky-400" />
              <span>{{ locale === 'km' ? 'ការជូនដំណឹងអំពីការបញ្ជាទិញ' : 'Order Notifications' }}</span>
            </p>
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 text-xs">
              <label class="flex items-center gap-2.5 rounded-xl border border-white/10 bg-[#070B16] p-3 cursor-pointer">
                <input v-model="notifyInApp" type="checkbox" class="accent-sky-400 h-4 w-4" />
                <span class="text-slate-200">In-App Alerts</span>
              </label>
              <label class="flex items-center gap-2.5 rounded-xl border border-white/10 bg-[#070B16] p-3 cursor-pointer">
                <input v-model="notifyEmail" type="checkbox" class="accent-sky-400 h-4 w-4" />
                <span class="text-slate-200">Email Receipts</span>
              </label>
              <label class="flex items-center gap-2.5 rounded-xl border border-white/10 bg-[#070B16] p-3 cursor-pointer">
                <input v-model="notifyTelegram" type="checkbox" class="accent-sky-400 h-4 w-4" />
                <span class="text-slate-200">Telegram Updates</span>
              </label>
            </div>
          </div>

          <button type="submit" :disabled="savingProfile" class="btn-primary-glow disabled:opacity-50 rounded-xl px-6 py-3 text-xs sm:text-sm font-bold text-white inline-flex items-center gap-2">
            <Loader2 v-if="savingProfile" class="h-4 w-4 animate-spin" />
            <Save v-else class="h-4 w-4" />
            <span>{{ locale === 'km' ? 'រក្សាទុកការផ្លាស់ប្តូរ' : 'Save Changes' }}</span>
          </button>
        </form>
      </div>

      <!-- TAB 3: SUPPORT TICKETS -->
      <div v-else-if="activeTab === 'tickets'" class="glass-card rounded-3xl p-5 sm:p-7 space-y-5">
        <div class="flex items-center justify-between">
          <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
            <Headphones class="h-5 w-5 text-sky-400" />
            <span>{{ locale === 'km' ? 'សំបុត្រជំនួយរបស់អ្នក' : 'Your Support Tickets' }}</span>
          </h2>
          <NuxtLink to="/contact" class="rounded-xl btn-primary-glow px-3.5 py-2 text-xs font-bold text-white">
            {{ locale === 'km' ? '+ បើកសំបុត្រថ្មី' : '+ New Ticket' }}
          </NuxtLink>
        </div>

        <div v-if="tickets.length === 0" class="rounded-2xl border border-dashed border-white/15 p-8 text-center text-xs text-slate-400">
          {{ locale === 'km' ? 'អ្នកមិនទាន់មានសំបុត្រជំនួយណាមួយទេ។' : 'No support tickets opened yet.' }}
        </div>

        <div v-else class="space-y-3">
          <div v-for="t in tickets" :key="t.id" class="rounded-2xl border border-white/10 bg-[#070B16]/85 p-4 flex flex-wrap items-center justify-between gap-3">
            <div>
              <p class="font-mono text-xs text-sky-400 font-bold">{{ t.ticket_number }}</p>
              <p class="text-sm font-bold text-white mt-0.5">{{ t.subject }}</p>
            </div>
            <span class="rounded-full bg-sky-500/15 border border-sky-400/30 px-3 py-1 text-[10px] font-bold uppercase text-sky-300">
              {{ t.status }}
            </span>
          </div>
        </div>
      </div>

      <!-- TAB 4: SECURITY & 2FA MFA -->
      <div v-else-if="activeTab === 'security'" class="glass-card rounded-3xl p-5 sm:p-7 space-y-5">
        <h2 class="text-base sm:text-lg font-bold text-white flex items-center gap-2">
          <ShieldCheck class="h-5 w-5 text-emerald-400" />
          <span>{{ locale === 'km' ? 'សុវត្ថិភាពគណនី (Two-Factor Authentication)' : 'Account Security (2FA MFA)' }}</span>
        </h2>

        <div v-if="auth.user?.mfa_enabled" class="rounded-2xl border border-emerald-400/35 bg-emerald-500/10 p-5 flex items-center gap-3.5">
          <CheckCircle2 class="h-6 w-6 text-emerald-400 shrink-0" />
          <div>
            <p class="text-sm font-bold text-white">{{ locale === 'km' ? 'សុវត្ថិភាព 2FA ត្រូវបានបើកដំណើរការ!' : '2FA MFA is Active' }}</p>
            <p class="text-xs text-emerald-200/80 mt-0.5">{{ locale === 'km' ? 'គណនីរបស់អ្នកត្រូវបានការពារដោយកូដ 6 ខ្ទង់ពីកម្មវិធី Authenticator។' : 'Your account is protected with TOTP 6-digit verification codes.' }}</p>
          </div>
        </div>

        <div v-else class="space-y-4">
          <p class="text-xs sm:text-sm text-slate-300 leading-relaxed">
            {{ locale === 'km'
              ? 'បើកមុខងារ 2FA ជាមួយ Google Authenticator ឬ Authy ដើម្បីការពារគណនីរបស់អ្នកឱ្យកាន់តែមានសុវត្ថិភាពខ្ពស់។'
              : 'Enable TOTP 2FA using Google Authenticator or Authy to add an extra layer of protection to your account.' }}
          </p>

          <button
            v-if="!mfaSecret"
            type="button"
            :disabled="settingUpMfa"
            class="btn-primary-glow rounded-xl px-5 py-3 text-xs font-bold text-white inline-flex items-center gap-2"
            @click="startMfaSetup"
          >
            <KeyRound class="h-4 w-4" />
            <span>{{ locale === 'km' ? 'ចាប់ផ្តើមដំឡើង 2FA MFA' : 'Setup 2FA Authenticator' }}</span>
          </button>

          <div v-else class="rounded-2xl border border-sky-400/30 bg-[#070B16] p-5 space-y-4">
            <p class="text-xs font-bold text-sky-300">1. {{ locale === 'km' ? 'បញ្ចូលកូដ Secret នេះទៅក្នុង Google Authenticator:' : 'Add this secret key to your Authenticator app:' }}</p>
            <div class="flex items-center gap-2">
              <code class="flex-1 rounded-xl bg-white/5 px-3 py-2.5 font-mono text-xs text-white break-all">{{ mfaSecret }}</code>
              <button type="button" class="rounded-xl border border-white/15 px-3 py-2.5 text-xs font-bold text-sky-300" @click="copyMfaSecret">
                <Check v-if="copiedSecret" class="h-4 w-4 text-emerald-400" />
                <Copy v-else class="h-4 w-4" />
              </button>
            </div>
            <div class="flex flex-col sm:flex-row gap-2.5">
              <input
                v-model="mfaCode"
                type="text"
                maxlength="6"
                inputmode="numeric"
                placeholder="123456"
                class="flex-1 rounded-xl border border-white/15 bg-white/5 px-4 py-2.5 font-mono text-sm text-white focus:border-sky-400 focus:outline-none"
              />
              <button type="button" :disabled="settingUpMfa || mfaCode.length !== 6" class="btn-primary-glow disabled:opacity-50 rounded-xl px-5 py-2.5 text-xs font-bold text-white" @click="confirmMfa">
                {{ locale === 'km' ? 'ផ្ទៀងផ្ទាត់ & បើក 2FA' : 'Verify & Enable' }}
              </button>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>
