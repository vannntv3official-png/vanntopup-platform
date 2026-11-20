import { defineStore } from 'pinia';
import type { AuthUser } from '@topup/shared-types';

export const useAdminAuthStore = defineStore('adminAuth', {
  state: () => ({
    token: null as string | null,
    user: null as AuthUser | null,
  }),
  getters: {
    isAuthenticated: (state) => Boolean(state.token),
  },
  actions: {
    setSession(token: string, user: AuthUser) {
      this.token = token;
      this.user = user;
      if (import.meta.client) {
        localStorage.setItem('ktu_admin_token', token);
      }
    },
    clearSession() {
      this.token = null;
      this.user = null;
      if (import.meta.client) {
        localStorage.removeItem('ktu_admin_token');
      }
    },
    hydrate() {
      if (import.meta.client) {
        const saved = localStorage.getItem('ktu_admin_token');
        if (saved) this.token = saved;
      }
    },
  },
});
