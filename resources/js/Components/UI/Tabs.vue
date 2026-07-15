<template>
  <div class="ui-tabs" role="tablist">
    <button
      v-for="tab in tabs"
      :key="tab.key"
      :id="tabId(tab.key)"
      type="button"
      role="tab"
      class="ui-tab"
      :class="{ active: modelValue === tab.key }"
      :aria-selected="modelValue === tab.key"
      :tabindex="modelValue === tab.key ? 0 : -1"
      @click="select(tab.key)"
      @keydown="onKeydown"
    >
      {{ tab.label }}
    </button>
  </div>
</template>

<script setup>
const props = defineProps({
  modelValue: { type: String, required: true },
  tabs: { type: Array, required: true }, // [{ key, label }]
})
const emit = defineEmits(['update:modelValue'])

function tabId(key) {
  return `ui-tab-${key}`
}

function select(key) {
  emit('update:modelValue', key)
}

function onKeydown(e) {
  if (!['ArrowLeft', 'ArrowRight'].includes(e.key)) return
  e.preventDefault()
  const idx = props.tabs.findIndex((t) => t.key === props.modelValue)
  const delta = e.key === 'ArrowRight' ? 1 : -1
  const nextIdx = (idx + delta + props.tabs.length) % props.tabs.length
  const nextKey = props.tabs[nextIdx].key
  select(nextKey)
  document.getElementById(tabId(nextKey))?.focus()
}
</script>

<style scoped>
.ui-tabs {
  display: flex; gap: 6px; padding: 4px;
  background: var(--background); border-radius: var(--radius-md);
}
.ui-tab {
  flex: 1; padding: 10px; min-height: 40px;
  border: none; border-radius: calc(var(--radius-md) - 4px);
  background: transparent; color: var(--text-secondary);
  font-size: 12px; font-weight: 700; cursor: pointer;
  transition: background-color .15s ease, color .15s ease;
}
.ui-tab:hover:not(.active) { background: var(--surface-hover); }
.ui-tab.active { background: var(--surface); color: var(--primary); box-shadow: var(--shadow-sm); }
.ui-tab:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
</style>
