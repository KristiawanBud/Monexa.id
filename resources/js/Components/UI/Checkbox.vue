<template>
  <label class="ui-checkbox" :class="{ 'is-disabled': disabled }">
    <input
      type="checkbox"
      :checked="modelValue"
      :disabled="disabled"
      @change="$emit('update:modelValue', $event.target.checked)"
    />
    <span class="ui-checkbox-box" aria-hidden="true"></span>
    <span v-if="label" class="ui-checkbox-label">{{ label }}</span>
  </label>
</template>

<script setup>
defineProps({
  modelValue: { type: Boolean, default: false },
  label: { type: String, default: '' },
  disabled: { type: Boolean, default: false },
})
defineEmits(['update:modelValue'])
</script>

<style scoped>
.ui-checkbox { display: inline-flex; align-items: center; gap: 8px; cursor: pointer; }
.ui-checkbox.is-disabled { cursor: not-allowed; opacity: var(--disabled-opacity); }
.ui-checkbox input { position: absolute; opacity: 0; width: 18px; height: 18px; margin: 0; cursor: inherit; }
.ui-checkbox-box {
  width: 18px; height: 18px; flex-shrink: 0;
  border: 1.5px solid var(--border); border-radius: 5px;
  background: var(--surface);
  display: flex; align-items: center; justify-content: center;
  transition: background-color .15s ease, border-color .15s ease;
}
.ui-checkbox input:checked + .ui-checkbox-box { background: var(--primary); border-color: var(--primary); }
.ui-checkbox input:checked + .ui-checkbox-box::after {
  content: ''; width: 5px; height: 9px;
  border: solid #fff; border-width: 0 2px 2px 0;
  transform: rotate(45deg) translate(-1px, -1px);
}
.ui-checkbox input:focus-visible + .ui-checkbox-box { box-shadow: var(--shadow-focus); }
.ui-checkbox-label { font-size: 13px; color: var(--text-primary); }
</style>
