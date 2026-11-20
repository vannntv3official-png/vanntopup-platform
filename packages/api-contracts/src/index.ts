import { z } from 'zod';

export const currencySchema = z.enum(['USD', 'KHR']);
export const localeSchema = z.enum(['km', 'en']);

export const playerValidationRequestSchema = z.object({
  region_code: z.string().min(1).max(32),
  player_fields: z.record(z.string(), z.string().min(1).max(128)),
});

export const createQuoteRequestSchema = z.object({
  game_id: z.string().uuid(),
  package_id: z.string().uuid(),
  region_code: z.string().min(1).max(32),
  currency: currencySchema.default('USD'),
  payment_method: z.enum(['aba_khqr', 'aba_payway', 'mock_khqr']).default('mock_khqr'),
  coupon_code: z.string().trim().max(64).optional().nullable(),
});

export const createOrderRequestSchema = z.object({
  quote_id: z.string().uuid(),
  quote_signature: z.string().min(16),
  player_fields: z.record(z.string(), z.string().min(1).max(128)),
  customer_email: z.string().email().optional().nullable(),
  customer_phone: z.string().min(7).max(24).optional().nullable(),
  idempotency_key: z.string().min(8).max(128),
});

export const createPaymentSessionRequestSchema = z.object({
  provider: z.enum(['mock_payway', 'aba_payway']).default('mock_payway'),
  tracking_token: z.string().min(16).optional(),
});

export const loginRequestSchema = z.object({
  email: z.string().email(),
  password: z.string().min(8),
  mfa_code: z.string().length(6).optional(),
});

export const registerRequestSchema = z.object({
  name: z.string().min(2).max(120),
  email: z.string().email(),
  phone: z.string().min(8).max(24).optional().nullable(),
  password: z.string().min(10).max(128),
  password_confirmation: z.string().min(10).max(128),
  preferred_locale: localeSchema.default('km'),
  preferred_currency: currencySchema.default('USD'),
}).refine((data) => data.password === data.password_confirmation, {
  message: 'Passwords do not match',
  path: ['password_confirmation'],
});

export const supportTicketRequestSchema = z.object({
  order_number: z.string().max(64).optional().nullable(),
  tracking_token: z.string().max(128).optional().nullable(),
  subject: z.string().min(4).max(180),
  message: z.string().min(10).max(4000),
  contact_email: z.string().email().optional().nullable(),
});

export type PlayerValidationRequest = z.infer<typeof playerValidationRequestSchema>;
export type CreateQuoteRequest = z.infer<typeof createQuoteRequestSchema>;
export type CreateOrderRequest = z.infer<typeof createOrderRequestSchema>;
export type CreatePaymentSessionRequest = z.infer<typeof createPaymentSessionRequestSchema>;
export type LoginRequest = z.infer<typeof loginRequestSchema>;
export type RegisterRequest = z.infer<typeof registerRequestSchema>;
export type SupportTicketRequest = z.infer<typeof supportTicketRequestSchema>;
