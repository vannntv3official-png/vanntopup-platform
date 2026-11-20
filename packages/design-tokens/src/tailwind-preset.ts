import { colors, fonts } from './index';

export const tailwindPreset = {
  theme: {
    extend: {
      colors: {
        brand: {
          bg: colors.background,
          surface: colors.surface,
          elevated: colors.surfaceElevated,
          border: colors.border,
          primary: colors.primary,
          'primary-hover': colors.primaryHover,
          accent: colors.accent,
          'accent-hover': colors.accentHover,
          text: colors.text,
          muted: colors.textMuted,
          success: colors.success,
          warning: colors.warning,
          danger: colors.danger,
        },
      },
      fontFamily: {
        sans: ['Kantumruy Pro', 'Inter', 'system-ui', 'sans-serif'],
        khmer: ['Kantumruy Pro', 'system-ui', 'sans-serif'],
        latin: ['Inter', 'system-ui', 'sans-serif'],
        mono: ['JetBrains Mono', 'ui-monospace', 'monospace'],
      },
      boxShadow: {
        glow: '0 0 24px rgba(37, 99, 235, 0.28)',
        'glow-accent': '0 0 24px rgba(56, 189, 248, 0.25)',
      },
    },
  },
};

export default tailwindPreset;
