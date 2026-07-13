import { ref } from 'vue'
import { router } from '@inertiajs/vue3'

const VALID_THEMES = ['blue', 'green', 'dark']
const STORAGE_KEY = 'monexa_theme'
const DEFAULT_THEME = 'blue'

const currentTheme = ref(DEFAULT_THEME)

function sanitize(value) {
  return VALID_THEMES.includes(value) ? value : null
}

// Inertia menaruh initial page object (termasuk shared props) di
// data-page milik root element sebelum Vue mount — dipakai di sini supaya
// tema tersimpan di akun (user_profiles.theme) bisa dibaca tanpa menunggu
// Vue/Inertia selesai hydrate.
function resolveSharedTheme() {
  try {
    const raw = document.getElementById('app')?.dataset.page
    if (!raw) return null
    const page = JSON.parse(raw)
    return sanitize(page?.props?.theme)
  } catch {
    return null
  }
}

function resolvePrefersDark() {
  if (typeof window.matchMedia !== 'function') return null
  return window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : null
}

function resolveInitialTheme() {
  const params = new URLSearchParams(window.location.search)
  const queryTheme = sanitize(params.get('theme'))
  if (queryTheme) return queryTheme

  const storedTheme = sanitize(window.localStorage.getItem(STORAGE_KEY))
  if (storedTheme) return storedTheme

  const sharedTheme = resolveSharedTheme()
  if (sharedTheme) {
    window.localStorage.setItem(STORAGE_KEY, sharedTheme)
    return sharedTheme
  }

  return (
    resolvePrefersDark() ??
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

function setTheme(name) {
  const theme = sanitize(name) ?? DEFAULT_THEME
  window.localStorage.setItem(STORAGE_KEY, theme)
  applyTheme(theme)

  // Sinkron ke akun (user_profiles.theme) — best-effort, optimistic.
  // Kalau request gagal (mis. offline), tema tetap berubah secara lokal,
  // tidak di-revert (lihat docs/theming-guide.md).
  router.put(route('account.theme'), { theme }, {
    preserveScroll: true,
    preserveState: true,
    onError: () => {},
  })
}

export function useTheme() {
  return { currentTheme, setTheme }
}

export function initTheme() {
  applyTheme(resolveInitialTheme())
  return { currentTheme, setTheme }
}
