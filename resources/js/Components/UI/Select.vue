<template>
  <div class="ui-select-group">
    <label v-if="label" :for="fieldId" class="ui-select-label">{{ label }}</label>
    <select
      :id="fieldId"
      class="ui-select"
      :class="{ 'has-error': !!error }"
      :disabled="disabled"
      :value="modelValue"
      :aria-invalid="!!error"
      :aria-describedby="error ? errorId : undefined"
      @change="$emit('update:modelValue', $event.target.value)"
    >
      <option v-if="placeholder" value="" disabled>{{ placeholder }}</option>
      <option v-for="opt in options" :key="opt.value" :value="opt.value" :disabled="opt.disabled">
        {{ opt.label }}
      </option>
    </select>
    <span v-if="error" :id="errorId" class="field-error">{{ error }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  options: { type: Array, default: () => [] }, // [{ value, label, disabled? }]
  label: { type: String, default: '' },
  placeholder: { type: String, default: '' },
  error: { type: String, default: null },
  disabled: { type: Boolean, default: false },
  id: { type: String, default: '' },
})

defineEmits(['update:modelValue'])

const autoId = `ui-select-${Math.random().toString(36).slice(2, 9)}`
const fieldId = computed(() => props.id || autoId)
const errorId = computed(() => `${fieldId.value}-error`)
</script>

<style scoped>
.ui-select-group { width: 100%; display: flex; flex-direction: column; gap: 6px; }
.ui-select-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
.ui-select {
  width: 100%; padding: 12px 16px;
  border: 1.5px solid var(--border); border-radius: var(--radius-md);
  background: var(--surface); color: var(--text-primary);
  font-size: 14px; font-family: 'Inter', sans-serif;
  transition: border-color .15s ease;
}
.ui-select:hover:not(:disabled) { border-color: var(--primary-light); }
.ui-select:focus-visible { outline: none; border-color: var(--primary); box-shadow: var(--shadow-focus); }
.ui-select:disabled { background: var(--disabled-bg); color: var(--disabled-text); cursor: not-allowed; }
.ui-select.has-error { border-color: var(--danger); }
.field-error { font-size: 12px; color: var(--danger); }
</style>
