<template>
  <div
    class="ui-toast"
    :class="`ui-toast-${variant}`"
    :role="variant === 'danger' || variant === 'warning' ? 'alert' : 'status'"
  >
    <span>{{ message }}</span>
    <button type="button" class="ui-toast-dismiss" aria-label="Tutup notifikasi" @click="$emit('dismiss')">✕</button>
  </div>
</template>

<script setup>
defineProps({
  variant: { type: String, default: 'info' }, // success | danger | warning | info
  message: { type: String, required: true },
})
defineEmits(['dismiss'])
</script>

<style scoped>
.ui-toast {
  display: flex; align-items: center; gap: 12px;
  padding: 12px 22px; border-radius: 99px;
  font-size: 13px; font-weight: 600; color: #fff;
  box-shadow: var(--shadow-lg); white-space: nowrap;
  font-family: 'Plus Jakarta Sans', sans-serif;
  pointer-events: auto;
}
.ui-toast-success { background: var(--success); }
.ui-toast-danger  { background: var(--danger); }
.ui-toast-warning { background: var(--warning); color: var(--text-primary); }
.ui-toast-info    { background: var(--info); }
.ui-toast-dismiss { background: none; border: none; color: inherit; opacity: .8; cursor: pointer; font-size: 11px; padding: 4px; }
.ui-toast-dismiss:hover { opacity: 1; }
</style>
