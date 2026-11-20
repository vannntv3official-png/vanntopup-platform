export type CurrencyCode = 'USD' | 'KHR';
export type LocaleCode = 'km' | 'en';

export type OrderStatus =
  | 'draft'
  | 'awaiting_payment'
  | 'payment_verifying'
  | 'paid'
  | 'fulfilling'
  | 'completed'
  | 'failed'
  | 'cancelled'
  | 'expired'
  | 'needs_review'
  | 'refunded';

export type PaymentStatus =
  | 'pending'
  | 'verifying'
  | 'paid'
  | 'failed'
  | 'expired'
  | 'refunded'
  | 'partially_refunded';

export type FulfillmentStatus =
  | 'not_started'
  | 'queued'
  | 'processing'
  | 'succeeded'
  | 'failed'
  | 'uncertain'
  | 'needs_review'
  | 'cancelled';

export type FulfillmentType = 'direct_topup' | 'voucher' | 'manual_assisted';

export interface GameRegion {
  id: string;
  code: string;
  name_en: string;
  name_km: string;
  is_active: boolean;
}

export interface GameField {
  id: string;
  key: string;
  label_en: string;
  label_km: string;
  placeholder_en?: string | null;
  placeholder_km?: string | null;
  field_type: 'text' | 'number' | 'select';
  is_required: boolean;
  validation_regex?: string | null;
  options?: Array<{ value: string; label_en: string; label_km: string }> | null;
  sort_order: number;
}

export interface GamePackage {
  id: string;
  game_id: string;
  internal_sku: string;
  name_en: string;
  name_km: string;
  entitlement_description_en: string;
  entitlement_description_km: string;
  currency: CurrencyCode;
  selling_price_minor: number;
  display_price_usd_minor: number;
  display_price_khr_minor: number;
  bonus_label?: string | null;
  is_promotion_eligible: boolean;
  is_available: boolean;
  is_featured: boolean;
  fulfillment_type: FulfillmentType;
  region_codes: string[];
  version: number;
}

export interface Game {
  id: string;
  slug: string;
  name: string;
  category_slug: string;
  description_en: string;
  description_km: string;
  short_description_en: string;
  short_description_km: string;
  thumbnail_url: string;
  banner_url: string;
  publisher: string;
  fulfillment_type: FulfillmentType;
  supports_player_validation: boolean;
  is_active: boolean;
  is_featured: boolean;
  is_in_maintenance: boolean;
  maintenance_message_en?: string | null;
  maintenance_message_km?: string | null;
  regions: GameRegion[];
  fields: GameField[];
  packages?: GamePackage[];
}

export interface PlayerValidationResult {
  valid: boolean;
  supported: boolean;
  player_name?: string | null;
  region_code?: string | null;
  message: string;
  provider_mode?: 'mock' | 'live';
}

export interface PriceQuote {
  id: string;
  game_id: string;
  package_id: string;
  region_code: string;
  currency: CurrencyCode;
  subtotal_minor: number;
  discount_minor: number;
  payment_fee_minor: number;
  total_minor: number;
  exchange_rate_usd_to_khr: number;
  coupon_code?: string | null;
  expires_at: string;
  signature: string;
}

export interface OrderTimelineStep {
  code: string;
  label_en: string;
  label_km: string;
  status: 'completed' | 'active' | 'pending' | 'warning' | 'failed';
  timestamp?: string | null;
}

export interface OrderSummary {
  id: string;
  order_number: string;
  tracking_token?: string;
  status: OrderStatus;
  payment_status: PaymentStatus;
  fulfillment_status: FulfillmentStatus;
  game_name: string;
  game_slug: string;
  package_name_en: string;
  package_name_km: string;
  region_code: string;
  masked_player_identifier: string;
  currency: CurrencyCode;
  subtotal_minor: number;
  discount_minor: number;
  payment_fee_minor: number;
  total_minor: number;
  customer_email?: string | null;
  customer_phone?: string | null;
  created_at: string;
  paid_at?: string | null;
  completed_at?: string | null;
  expires_at?: string | null;
  timeline?: OrderTimelineStep[];
}

export interface PaymentSession {
  payment_intent_id: string;
  provider: string;
  provider_mode: 'mock' | 'sandbox' | 'live';
  status: PaymentStatus;
  amount_minor: number;
  currency: CurrencyCode;
  reference: string;
  qr_payload?: string | null;
  checkout_url?: string | null;
  expires_at: string;
  instructions_en: string;
  instructions_km: string;
}

export interface AuthUser {
  id: string;
  name: string;
  email: string;
  phone?: string | null;
  avatar_url?: string | null;
  auth_provider?: string;
  preferred_locale: LocaleCode;
  preferred_currency: CurrencyCode;
  roles: string[];
  permissions: string[];
  mfa_enabled: boolean;
}

/**
 * Formats integer minor units according to currency conventions:
 * - USD has 2 decimal places (100 cents = $1.00)
 * - KHR has 0 minor decimal places (integer Riel, e.g., 4,100 ៛)
 */
export function formatMoney(amountMinor: number, currency: CurrencyCode): string {
  if (currency === 'KHR') {
    return `${new Intl.NumberFormat('en-US', { maximumFractionDigits: 0 }).format(Math.round(amountMinor))} ៛`;
  }
  const major = amountMinor / 100;
  return new Intl.NumberFormat('en-US', {
    style: 'currency',
    currency: 'USD',
    minimumFractionDigits: 2,
    maximumFractionDigits: 2,
  }).format(major);
}
