import { ref } from 'vue'
import { router, usePage } from '@inertiajs/vue3'

const VALID_THEMES = ['blue', 'green', 'dark', 'system']
const STORAGE_KEY = 'monexa_theme'
const DEFAULT_THEME = 'blue'

const currentTheme = ref(DEFAULT_THEME)
const darkMedia = typeof window !== 'undefined' ? window.matchMedia('(prefers-color-scheme: dark)') : null
let systemListenerActive = false

function sanitize(value) {
  return VALID_THEMES.includes(value) ? value : null
}

function resolveSystemTheme() {
  return darkMedia?.matches ? 'dark' : 'blue'
}

// initTheme() jalan sebelum Inertia mount (supaya tema ter-set sebelum paint pertama),
// jadi usePage().props belum terisi di titik ini — baca langsung JSON halaman awal yang
// di-embed server lewat direktif @inertia (baik varian <div data-page> maupun
// <script data-page="app">), bukan lewat usePage().
function resolveSharedTheme() {
  try {
    const raw = document.getElementById('app')?.dataset?.page
      ?? document.querySelector('script[data-page="app"]')?.textContent
    if (!raw) return null
    return sanitize(JSON.parse(raw)?.props?.theme)
  } catch {
    return null
  }
}

function resolveInitialTheme() {
  const params = new URLSearchParams(window.location.search)
  return (
    sanitize(params.get('theme')) ??
    resolveSharedTheme() ??
    sanitize(window.localStorage.getItem(STORAGE_KEY)) ??
    sanitize(import.meta.env.VITE_DEFAULT_THEME) ??
    DEFAULT_THEME
  )
}

function onSystemPreferenceChange() {
  if (currentTheme.value === 'system') applyTheme('system')
}

function syncSystemListener(theme) {
  if (!darkMedia) return
  if (theme === 'system' && !systemListenerActive) {
    darkMedia.addEventListener('change', onSystemPreferenceChange)
    systemListenerActive = true
  } else if (theme !== 'system' && systemListenerActive) {
    darkMedia.removeEventListener('change', onSystemPreferenceChange)
    systemListenerActive = false
  }
}

function applyTheme(name) {
  const theme = sanitize(name) ?? DEFAULT_THEME
  currentTheme.value = theme
  const resolved = theme === 'system' ? resolveSystemTheme() : theme

  if (document.documentElement.dataset.theme !== resolved) {
    document.documentElement.dataset.theme = resolved
    document.documentElement.classList.toggle('dark', resolved === 'dark')
  }

  syncSystemListener(theme)
}

function persistToServer(theme) {
  if (!usePage().props?.auth?.user) return
  router.put(route('account.theme'), { theme }, { preserveScroll: true, preserveState: true })
}

function setTheme(name) {
  const theme = sanitize(name) ?? DEFAULT_THEME
  window.localStorage.setItem(STORAGE_KEY, theme)
  applyTheme(theme)
  persistToServer(theme)
}

export function useTheme() {
  return { currentTheme, setTheme }
}

export function initTheme() {
  applyTheme(resolveInitialTheme())
  return { currentTheme, setTheme }
}
