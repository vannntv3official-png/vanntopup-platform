import { defineStore } from 'pinia';
import type { CurrencyCode } from '@topup/shared-types';

export const usePreferencesStore = defineStore('preferences', {
  state: () => ({
    currency: 'USD' as CurrencyCode,
    exchangeRateUsdToKhr: 4100,
  }),
  actions: {
    setCurrency(currency: CurrencyCode) {
      this.currency = currency;
    },
    setExchangeRate(rate: number) {
      if (rate > 0) {
        this.exchangeRateUsdToKhr = rate;
      }
    },
  },
});
