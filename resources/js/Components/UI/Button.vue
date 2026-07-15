<template>
  <button
    :type="type"
    class="ui-btn"
    :class="[`ui-btn-${variant}`, `ui-btn-${size}`, { 'is-loading': loading }]"
    :disabled="disabled || loading"
    @click="onClick"
  >
    <span v-if="loading" class="ui-btn-spinner" aria-hidden="true"></span>
    <slot />
  </button>
</template>

<script setup>
const props = defineProps({
  variant: { type: String, default: 'primary' }, // primary | secondary | danger | ghost
  size: { type: String, default: 'md' }, // sm | md | lg
  disabled: { type: Boolean, default: false },
  loading: { type: Boolean, default: false },
  type: { type: String, default: 'button' },
})

const emit = defineEmits(['click'])

function onClick(e) {
  if (props.disabled || props.loading) return
  emit('click', e)
}
</script>

<style scoped>
.ui-btn {
  display: inline-flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  font-family: 'Plus Jakarta Sans', sans-serif;
  font-weight: 700;
  border-radius: var(--radius-md);
  border: 1.5px solid transparent;
  cursor: pointer;
  transition: background-color .15s ease, border-color .15s ease, color .15s ease, transform .05s ease;
}
.ui-btn:active:not(:disabled) { transform: scale(.98); }
.ui-btn:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
.ui-btn:disabled { cursor: not-allowed; opacity: var(--disabled-opacity); }

/* Size */
.ui-btn-sm { min-height: 36px; padding: 6px 14px; font-size: 12px; }
.ui-btn-md { min-height: 44px; padding: 10px 18px; font-size: 13px; }
.ui-btn-lg { min-height: 52px; padding: 14px 22px; font-size: 15px; }

/* Variant */
.ui-btn-primary { background: var(--primary); color: #fff; }
.ui-btn-primary:hover:not(:disabled) { background: var(--primary-hover); }

.ui-btn-secondary { background: var(--surface); color: var(--text-primary); border-color: var(--border); }
.ui-btn-secondary:hover:not(:disabled) { background: var(--surface-hover); }

.ui-btn-danger { background: var(--danger); color: #fff; }
.ui-btn-danger:hover:not(:disabled) { background: var(--danger-hover); }

.ui-btn-ghost { background: transparent; color: var(--text-secondary); }
.ui-btn-ghost:hover:not(:disabled) { background: var(--surface-hover); }

.ui-btn-spinner {
  width: 14px; height: 14px; border-radius: 50%;
  border: 2px solid rgba(255, 255, 255, .4);
  border-top-color: #fff;
  animation: ui-btn-spin .6s linear infinite;
}
.ui-btn-secondary .ui-btn-spinner, .ui-btn-ghost .ui-btn-spinner {
  border-color: var(--border); border-top-color: var(--text-secondary);
}

@keyframes ui-btn-spin { to { transform: rotate(360deg); } }
</style>
