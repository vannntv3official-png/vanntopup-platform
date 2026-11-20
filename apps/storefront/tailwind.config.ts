import type { Config } from 'tailwindcss';

export default <Partial<Config>>{
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
    './composables/**/*.{js,ts}',
    './app.vue',
  ],
  theme: {
    extend: {
      colors: {
        brand: {
          bg: '#0B1020',
          surface: '#151E32',
          elevated: '#1C2841',
          border: '#253350',
          primary: '#2563EB',
          'primary-hover': '#1D4ED8',
          accent: '#38BDF8',
          text: '#F8FAFC',
          muted: '#94A3B8',
          success: '#10B981',
          warning: '#F59E0B',
          danger: '#EF4444',
        },
      },
      fontFamily: {
        sans: ['Outfit', 'Kantumruy Pro', 'system-ui', 'sans-serif'],
      },
    },
  },
};
