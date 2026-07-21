import { ref } from 'vue'

const VALID_THEMES = ['blue', 'green', 'dark']
const STORAGE_KEY = 'monexa_theme'
const DEFAULT_THEME = 'blue'

const currentTheme = ref(DEFAULT_THEME)

function sanitize(value) {
  return VALID_THEMES.includes(value) ? value : null
}

function resolveInitialTheme() {
  const params = new URLSearchParams(window.location.search)
  return (
    sanitize(params.get('theme')) ??
    sanitize(window.localStorage.getItem(STORAGE_KEY)) ??
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
}

export function useTheme() {
  return { currentTheme, setTheme }
}

export function initTheme() {
  applyTheme(resolveInitialTheme())
  return { currentTheme, setTheme }
}
