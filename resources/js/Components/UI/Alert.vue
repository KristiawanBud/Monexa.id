<template>
  <div
    v-if="visible"
    class="ui-alert"
    :class="`ui-alert-${variant}`"
    :role="variant === 'danger' || variant === 'warning' ? 'alert' : 'status'"
  >
    <span class="ui-alert-icon" aria-hidden="true">{{ icon }}</span>
    <div class="ui-alert-message"><slot /></div>
    <button
      v-if="dismissible"
      type="button"
      class="ui-alert-dismiss"
      aria-label="Tutup peringatan"
      @click="visible = false"
    >✕</button>
  </div>
</template>

<script setup>
import { ref, computed } from 'vue'

const props = defineProps({
  variant: { type: String, default: 'info' }, // success | danger | warning | info
  dismissible: { type: Boolean, default: false },
})

const visible = ref(true)

const icon = computed(() => ({
  success: '✅', danger: '❌', warning: '⚠️', info: 'ℹ️',
}[props.variant] ?? 'ℹ️'))
</script>

<style scoped>
.ui-alert {
  display: flex; align-items: flex-start; gap: 10px;
  padding: 12px 14px; border-radius: var(--radius-md);
  font-size: 13px; line-height: 1.5;
}
.ui-alert-icon { flex-shrink: 0; }
.ui-alert-message { flex: 1; }
.ui-alert-dismiss {
  flex-shrink: 0; background: none; border: none; cursor: pointer;
  font-size: 12px; color: inherit; opacity: .6; padding: 2px;
}
.ui-alert-dismiss:hover { opacity: 1; }

.ui-alert-success { background: var(--success-bg); color: var(--success); }
.ui-alert-danger  { background: var(--danger-bg); color: var(--danger); }
.ui-alert-warning { background: var(--amber-bg); color: var(--warning); }
.ui-alert-info    { background: var(--primary-bg); color: var(--info); }
</style>
