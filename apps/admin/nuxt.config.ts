export default defineNuxtConfig({
  compatibilityDate: '2025-03-01',
  devtools: { enabled: false },
  ssr: false,
  modules: [
    '@nuxtjs/tailwindcss',
    '@pinia/nuxt',
    '@vueuse/nuxt',
  ],
  runtimeConfig: {
    public: {
      apiBase: process.env.NUXT_PUBLIC_API_BASE || 'http://127.0.0.1:8000/api/v1',
      appName: 'VannTopUp Operations Admin',
    },
  },
  app: {
    head: {
      title: 'VannTopUp Admin Console',
      meta: [
        { name: 'viewport', content: 'width=device-width, initial-scale=1' },
        { name: 'robots', content: 'noindex, nofollow' },
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
