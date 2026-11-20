import { useAuthStore } from '~/stores/auth';

export function useApi() {
  const config = useRuntimeConfig();
  const auth = useAuthStore();

  async function request<T>(path: string, options: Parameters<typeof $fetch>[1] = {}): Promise<T> {
    const headers: Record<string, string> = {
      Accept: 'application/json',
      ...((options?.headers as Record<string, string>) || {}),
    };

    if (auth.token) {
      headers.Authorization = `Bearer ${auth.token}`;
    }

    return await $fetch<T>(`${config.public.apiBase}${path}`, {
      ...options,
      headers,
    });
  }

  return { request, apiBase: config.public.apiBase };
}
