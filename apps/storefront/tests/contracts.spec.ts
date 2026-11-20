import { describe, it, expect } from 'vitest';
import { formatMoney } from '@topup/shared-types';
import { createQuoteRequestSchema, createOrderRequestSchema } from '@topup/api-contracts';

describe('Storefront currency & API contracts', () => {
  it('formats USD cents and KHR integer Riel accurately', () => {
    expect(formatMoney(190, 'USD')).toBe('$1.90');
    expect(formatMoney(6150, 'KHR')).toBe('6,150 ៛');
  });

  it('validates price quote request schema', () => {
    const valid = createQuoteRequestSchema.safeParse({
      game_id: '550e8400-e29b-41d4-a716-446655440000',
      package_id: '550e8400-e29b-41d4-a716-446655440001',
      region_code: 'KH',
      currency: 'USD',
      payment_method: 'mock_khqr',
    });
    expect(valid.success).toBe(true);
  });

  it('rejects malformed order request without quote signature', () => {
    const invalid = createOrderRequestSchema.safeParse({
      quote_id: '550e8400-e29b-41d4-a716-446655440000',
      quote_signature: 'short',
      player_fields: { user_id: '123456789' },
      idempotency_key: 'abc',
    });
    expect(invalid.success).toBe(false);
  });
});
