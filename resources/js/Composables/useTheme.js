import { ref } from 'vue'

const VALID_THEMES = ['blue', 'green', 'dark']
const STORAGE_KEY = 'monexa_theme'
const DEFAULT_THEME = 'blue'

function sanitizeTheme(value) {
  return VALID_THEMES.includes(value) ? value : null
}

function resolveInitialTheme() {
  const urlTheme = sanitizeTheme(new URLSearchParams(window.location.search).get('theme'))
  if (urlTheme) return urlTheme

  const storedTheme = sanitizeTheme(window.localStorage.getItem(STORAGE_KEY))
  if (storedTheme) return storedTheme

  const envTheme = sanitizeTheme(import.meta.env.VITE_DEFAULT_THEME)
  if (envTheme) return envTheme

  return DEFAULT_THEME
}

function applyTheme(name) {
  document.documentElement.dataset.theme = name
  document.documentElement.classList.toggle('dark', name === 'dark')
}

export function useTheme() {
  const currentTheme = ref(resolveInitialTheme())
  applyTheme(currentTheme.value)
  window.localStorage.setItem(STORAGE_KEY, currentTheme.value)

  const setTheme = (name) => {
    const safeName = sanitizeTheme(name) ?? DEFAULT_THEME
    currentTheme.value = safeName
    applyTheme(safeName)
    window.localStorage.setItem(STORAGE_KEY, safeName)
  }

  return { currentTheme, setTheme }
}
