<template>
  <button type="button" class="export-btn" aria-label="Export transaksi ke CSV" @click="handleExport">
    ⬇️ Export CSV
  </button>
</template>

<script setup>
import { trackEvent } from '@/lib/analytics'

const props = defineProps({
  filters: { type: Object, default: () => ({}) },
})

const handleExport = () => {
  trackEvent('dompet_export_csv', { filters: props.filters })
  window.location.href = route('dompet.export', props.filters)
}
</script>

<style scoped>
.export-btn {
  background: var(--surface);
  border: 1.5px solid var(--border);
  padding: 10px 16px;
  border-radius: var(--radius-md);
  font-size: 12px;
  font-weight: 700;
  color: var(--text-secondary);
  cursor: pointer;
  white-space: nowrap;
  min-height: 44px;
  transition: border-color .15s, color .15s;
}
.export-btn:hover { border-color: var(--primary); color: var(--primary); }
.export-btn:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
</style>
