<template>
  <div class="cat-chip-scroll" role="group" aria-label="Filter kategori cepat (multi-pilih)">
    <button
      type="button"
      :class="['chip', 'cat-chip', { active: selected.length === 0 }]"
      :aria-pressed="selected.length === 0"
      @click="$emit('select', null)"
    >
      Semua
    </button>
    <button
      v-for="c in categories"
      :key="c.id"
      type="button"
      :class="['chip', 'cat-chip', { active: selected.includes(c.id) }]"
      :aria-pressed="selected.includes(c.id)"
      @click="$emit('select', c.id)"
    >
      {{ c.emoji }} {{ c.name }}
    </button>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  categories: { type: Array, default: () => [] },
  // Multi-select: array kosong berarti "Semua kategori". Boleh juga diberi
  // string/null tunggal untuk kompatibilitas pemanggil lama.
  modelValue: { type: [Array, String], default: () => [] },
})
defineEmits(['select'])

const selected = computed(() => (Array.isArray(props.modelValue) ? props.modelValue : (props.modelValue ? [props.modelValue] : [])))
</script>

<style scoped>
.cat-chip-scroll {
  display: flex;
  gap: 8px;
  overflow-x: auto;
  padding: 2px 2px 10px;
  scrollbar-width: none;
}
.cat-chip-scroll::-webkit-scrollbar { display: none; }
.cat-chip {
  flex-shrink: 0;
  white-space: nowrap;
  min-height: 36px;
}
</style>
