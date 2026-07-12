<template>
  <div class="cat-chip-scroll" role="group" aria-label="Filter kategori cepat">
    <button
      type="button"
      :class="['chip', 'cat-chip', { active: modelValue === null }]"
      @click="$emit('select', null)"
    >
      Semua
    </button>
    <button
      v-for="c in categories"
      :key="c.id"
      type="button"
      :class="['chip', 'cat-chip', { active: modelValue === c.id }]"
      @click="$emit('select', modelValue === c.id ? null : c.id)"
    >
      {{ c.emoji }} {{ c.name }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  categories: { type: Array, default: () => [] },
  modelValue: { type: String, default: null },
})
defineEmits(['select'])
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
