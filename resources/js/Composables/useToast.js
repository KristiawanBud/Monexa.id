import { ref } from 'vue'

// Singleton queue module-level, dipakai bersama oleh semua pemanggil useToast()
// di seluruh aplikasi (ToastContainer.vue merender queue ini).
const toasts = ref([])
let nextId = 1

function push({ variant = 'info', message, duration = 4000 }) {
  const id = nextId++
  toasts.value.push({ id, variant, message })
  if (duration > 0) {
    setTimeout(() => dismiss(id), duration)
  }
  return id
}

function dismiss(id) {
  toasts.value = toasts.value.filter((t) => t.id !== id)
}

export function useToast() {
  return { toasts, push, dismiss }
}
