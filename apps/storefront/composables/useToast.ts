export interface ToastItem {
  id: number;
  message: string;
  type: 'success' | 'error' | 'info';
}

export function useToast() {
  const toasts = useState<ToastItem[]>('ktu_toasts', () => []);

  function push(message: string, type: ToastItem['type'] = 'info', durationMs = 3200) {
    const id = Date.now() + Math.floor(Math.random() * 1000);
    toasts.value.push({ id, message, type });
    setTimeout(() => {
      toasts.value = toasts.value.filter((t) => t.id !== id);
    }, durationMs);
  }

  return {
    toasts,
    success: (msg: string) => push(msg, 'success'),
    error: (msg: string) => push(msg, 'error'),
    info: (msg: string) => push(msg, 'info'),
  };
}
