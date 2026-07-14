import { ref } from 'vue'
import { usePage, router } from '@inertiajs/vue3'

const VALID_THEMES = ['blue', 'green', 'dark']
const STORAGE_KEY = 'monexa_theme'
const DEFAULT_THEME = 'blue'

const currentTheme = ref(DEFAULT_THEME)

function sanitize(value) {
  return VALID_THEMES.includes(value) ? value : null
}

// Sebelum Inertia mount, usePage() belum terisi — baca langsung dari
// payload awal yang di-embed Inertia di elemen root (data-page), supaya
// shared prop `theme` tetap ikut resolusi tema paling pertama render.
function resolveSharedTheme() {
  try {
    const el = document.getElementById('app')
    const raw = el?.dataset?.page
    if (!raw) return null
    return sanitize(JSON.parse(raw)?.props?.theme)
  } catch {
    return null
  }
}

function prefersDarkScheme() {
  return typeof window.matchMedia === 'function'
    && window.matchMedia('(prefers-color-scheme: dark)').matches
}

function resolveInitialTheme() {
  const params = new URLSearchParams(window.location.search)
  return (
    sanitize(params.get('theme')) ??
    sanitize(window.localStorage.getItem(STORAGE_KEY)) ??
    resolveSharedTheme() ??
    (prefersDarkScheme() ? 'dark' : null) ??
    sanitize(import.meta.env.VITE_DEFAULT_THEME) ??
    DEFAULT_THEME
  )
}

function applyTheme(name) {
  const theme = sanitize(name) ?? DEFAULT_THEME
  currentTheme.value = theme
  document.documentElement.dataset.theme = theme
  document.documentElement.classList.toggle('dark', theme === 'dark')
}

function isLoggedIn() {
  return !!usePage().props.value?.auth?.user
}

function setTheme(name) {
  const theme = sanitize(name) ?? DEFAULT_THEME
  window.localStorage.setItem(STORAGE_KEY, theme)
  applyTheme(theme)

  if (isLoggedIn()) {
    router.put(route('account.theme'), { theme }, { preserveScroll: true, preserveState: true })
  }
}

export function useTheme() {
  return { currentTheme, setTheme }
}

export function initTheme() {
  applyTheme(resolveInitialTheme())
  return { currentTheme, setTheme }
}
