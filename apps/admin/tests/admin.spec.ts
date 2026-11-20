import { describe, it, expect } from 'vitest';
import { formatMoney } from '@topup/shared-types';

describe('Admin console utilities', () => {
  it('formats revenue minor units cleanly', () => {
    expect(formatMoney(12500, 'USD')).toBe('$125.00');
    expect(formatMoney(410000, 'KHR')).toBe('410,000 ៛');
  });
});
