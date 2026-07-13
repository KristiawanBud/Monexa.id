<template>
  <div v-if="enabled" class="theme-toggle">
    <button
      v-for="opt in options"
      :key="opt.value"
      type="button"
      :class="['tt-btn', { active: currentTheme === opt.value }]"
      :aria-pressed="currentTheme === opt.value"
      @click="select(opt.value)"
    >
      <span class="tt-swatch" :style="`background:${opt.swatch}`"></span>
      {{ opt.label }}
    </button>
  </div>
</template>

<script setup>
import { useTheme } from '@/Composables/useTheme'
import { trackEvent } from '@/lib/analytics'

const enabled = import.meta.env.VITE_ENABLE_THEME_TOGGLE === 'true'
const { currentTheme, setTheme } = useTheme()

const options = [
  { value: 'blue', label: 'Biru', swatch: '#2563EB' },
  { value: 'green', label: 'Hijau', swatch: '#16A34A' },
  { value: 'dark', label: 'Gelap', swatch: '#0F172A' },
]

function select(name) {
  setTheme(name)
  trackEvent('dompet_theme_change', { theme: name })
}
</script>

<style scoped>
.theme-toggle { display: flex; gap: 8px; flex-wrap: wrap; }
.tt-btn {
  display: flex; align-items: center; gap: 8px;
  padding: 10px 14px; min-height: 44px;
  border: 1.5px solid var(--border); background: var(--surface);
  border-radius: var(--radius-md); font-size: 13px; font-weight: 600;
  color: var(--text-secondary); cursor: pointer;
}
.tt-btn.active { border-color: var(--primary); background: var(--primary-bg); color: var(--primary); }
.tt-btn:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
.tt-swatch { width: 14px; height: 14px; border-radius: 50%; flex-shrink: 0; box-shadow: inset 0 0 0 1.5px rgba(0,0,0,.08); }
</style>
