<template>
  <div class="empty-state">
    <div class="empty-state-icon">{{ icon }}</div>
    <div class="empty-state-title">{{ title }}</div>
    <button v-if="actionLabel" type="button" class="btn-primary empty-state-action" @click="$emit('action')">
      {{ actionLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  icon: { type: String, default: '📭' },
  title: { type: String, required: true },
  actionLabel: { type: String, default: null },
})
defineEmits(['action'])
</script>

<style scoped>
.empty-state { text-align: center; padding: 32px 20px; }
.empty-state-icon { font-size: 40px; margin-bottom: 10px; }
.empty-state-title { font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 14px; }
.empty-state-action { max-width: 220px; margin: 0 auto; }
</style>
