import type { Config } from 'tailwindcss';

export default <Partial<Config>>{
  content: [
    './components/**/*.{vue,js,ts}',
    './layouts/**/*.vue',
    './pages/**/*.vue',
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
          accent: '#38BDF8',
          text: '#F8FAFC',
          muted: '#94A3B8',
        },
      },
      fontFamily: {
        sans: ['Inter', 'Kantumruy Pro', 'system-ui', 'sans-serif'],
      },
    },
  },
};
