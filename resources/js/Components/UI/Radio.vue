<template>
  <button
    type="button"
    class="ui-radio"
    :class="{ 'is-disabled': disabled }"
    role="radio"
    :aria-checked="checked"
    :disabled="disabled"
    @click="$emit('select')"
  >
    <span class="ui-radio-dot" aria-hidden="true"></span>
    <span v-if="label" class="ui-radio-label">{{ label }}</span>
  </button>
</template>

<script setup>
defineProps({
  checked: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})
defineEmits(['select'])
</script>

<style scoped>
.ui-radio {
  display: inline-flex; align-items: center; gap: 8px;
  background: none; border: none; padding: 0; cursor: pointer;
  font-family: 'Inter', sans-serif;
}
.ui-radio.is-disabled { cursor: not-allowed; opacity: var(--disabled-opacity); }
.ui-radio-dot {
  width: 18px; height: 18px; flex-shrink: 0; border-radius: 50%;
  border: 1.5px solid var(--border); background: var(--surface);
  display: flex; align-items: center; justify-content: center;
  transition: border-color .15s ease;
}
.ui-radio[aria-checked='true'] .ui-radio-dot { border-color: var(--primary); }
.ui-radio[aria-checked='true'] .ui-radio-dot::after {
  content: ''; width: 9px; height: 9px; border-radius: 50%; background: var(--primary);
}
.ui-radio:focus-visible .ui-radio-dot { box-shadow: var(--shadow-focus); }
.ui-radio-label { font-size: 13px; color: var(--text-primary); }
</style>
