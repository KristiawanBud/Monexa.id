<template>
  <div class="ui-input-group">
    <label v-if="label" :for="fieldId" class="ui-input-label">{{ label }}</label>
    <input
      :id="fieldId"
      class="ui-input"
      :class="{ 'has-error': !!error }"
      :type="type"
      :placeholder="placeholder"
      :disabled="disabled"
      :value="modelValue"
      :aria-invalid="!!error"
      :aria-describedby="error ? errorId : undefined"
      @input="$emit('update:modelValue', $event.target.value)"
    />
    <span v-if="error" :id="errorId" class="field-error">{{ error }}</span>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  modelValue: { type: [String, Number], default: '' },
  label: { type: String, default: '' },
  type: { type: String, default: 'text' },
  error: { type: String, default: null },
  disabled: { type: Boolean, default: false },
  placeholder: { type: String, default: '' },
  id: { type: String, default: '' },
})

defineEmits(['update:modelValue'])

const autoId = `ui-input-${Math.random().toString(36).slice(2, 9)}`
const fieldId = computed(() => props.id || autoId)
const errorId = computed(() => `${fieldId.value}-error`)
</script>

<style scoped>
.ui-input-group { width: 100%; display: flex; flex-direction: column; gap: 6px; }
.ui-input-label { font-size: 13px; font-weight: 600; color: var(--text-secondary); }
.ui-input {
  width: 100%; padding: 12px 16px;
  border: 1.5px solid var(--border); border-radius: var(--radius-md);
  background: var(--surface); color: var(--text-primary);
  font-size: 14px; font-family: 'Inter', sans-serif;
  transition: border-color .15s ease;
}
.ui-input::placeholder { color: var(--text-faint); }
.ui-input:hover:not(:disabled) { border-color: var(--primary-light); }
.ui-input:focus-visible { outline: none; border-color: var(--primary); box-shadow: var(--shadow-focus); }
.ui-input:disabled { background: var(--disabled-bg); color: var(--disabled-text); cursor: not-allowed; }
.ui-input.has-error { border-color: var(--danger); }
.field-error { font-size: 12px; color: var(--danger); }
</style>
