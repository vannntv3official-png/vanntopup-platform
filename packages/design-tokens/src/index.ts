export const colors = {
  background: '#0B1020',
  surface: '#151E32',
  surfaceElevated: '#1C2841',
  border: '#253350',
  primary: '#2563EB',
  primaryHover: '#1D4ED8',
  accent: '#38BDF8',
  accentHover: '#0EA5E9',
  text: '#F8FAFC',
  textMuted: '#94A3B8',
  success: '#10B981',
  warning: '#F59E0B',
  danger: '#EF4444',
  info: '#38BDF8',
} as const;

export const fonts = {
  khmer: '"Kantumruy Pro", system-ui, sans-serif',
  latin: '"Inter", system-ui, sans-serif',
  sans: '"Kantumruy Pro", "Inter", system-ui, -apple-system, sans-serif',
  mono: '"JetBrains Mono", ui-monospace, SFMono-Regular, monospace',
} as const;

export const spacing = {
  pageMaxWidth: '1280px',
  headerHeight: '72px',
  cardRadius: '16px',
  buttonRadius: '12px',
} as const;

export const motion = {
  fast: '150ms',
  normal: '240ms',
  slow: '360ms',
  easing: 'cubic-bezier(0.22, 1, 0.36, 1)',
} as const;

export const designTokens = {
  colors,
  fonts,
  spacing,
  motion,
} as const;

export type DesignTokens = typeof designTokens;
