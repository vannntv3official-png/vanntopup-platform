export interface AdminToast {
  id: number;
  message: string;
  type: 'success' | 'error' | 'info';
}

export function useAdminToast() {
  const toasts = useState<AdminToast[]>('admin_toasts', () => []);

  function push(message: string, type: AdminToast['type'] = 'info', durationMs = 3200) {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    toasts.value.push({ id, message, type });
    setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== id);
    }, durationMs);
  }

  return {
    toasts,
    success: (m: string) => push(m, 'success'),
    error: (m: string) => push(m, 'error'),
    info: (m: string) => push(m, 'info'),
  };
}
