export default defineNuxtConfig({
  compatibilityDate: '2025-03-01',
  devtools: { enabled: false },
  modules: [
    '@nuxtjs/tailwindcss',
    '@pinia/nuxt',
    '@vueuse/nuxt',
    '@nuxtjs/i18n',
  ],
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8000/api/v1',
      appName: process.env.NUXT_PUBLIC_APP_NAME || 'VannTopUp',
      appEnv: process.env.NUXT_PUBLIC_APP_ENV || 'local',
    },
  },
  i18n: {
    defaultLocale: 'km',
    strategy: 'no_prefix',
    locales: [
      { code: 'km', name: 'ភាសាខ្មែរ', file: 'km.json' },
      { code: 'en', name: 'English', file: 'en.json' },
    ],
    langDir: 'locales',
    detectBrowserLanguage: false,
  },
  app: {
    pageTransition: { name: 'page', mode: 'out-in' },
    head: {
      title: 'VannTopUp — Cambodia Automatic Game Top-Up',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        {
          name: 'description',
          content: 'VannTopUp — Fast, secure, automatic game currency top-up platform in Cambodia for Mobile Legends, Free Fire, PUBG Mobile, and Honor of Kings.',
        },
        { name: 'theme-color', content: '#0B1020' },
      ],
      link: [
        { rel: 'icon', type: 'image/jpeg', href: '/images/brand/vanntopup-logo.jpg' },
        { rel: 'preconnect', href: 'https://fonts.googleapis.com' },
        { rel: 'preconnect', href: 'https://fonts.gstatic.com', crossorigin: '' },
        {
          rel: 'stylesheet',
          href: 'https://fonts.googleapis.com/css2?family=Kantumruy+Pro:wght@400;500;600;700&family=Outfit:wght@400;500;600;700;800&display=swap',
        },
      ],
    },
  },
  css: ['~/assets/css/main.css'],
  typescript: {
    strict: true,
    typeCheck: false,
  },
});
