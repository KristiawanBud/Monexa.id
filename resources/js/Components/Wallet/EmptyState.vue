<template>
  <div class="empty-state">
    <div class="empty-illust">{{ icon }}</div>
    <div class="empty-text">{{ title }}</div>
    <button
      v-if="actionLabel"
      type="button"
      class="btn-primary empty-action"
      @click="$emit('action')"
    >
      {{ actionLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  icon: { type: String, default: '📝' },
  title: { type: String, required: true },
  actionLabel: { type: String, default: null },
})
defineEmits(['action'])
</script>

<style scoped>
.empty-state { text-align: center; padding: 32px 20px; }
.empty-illust { font-size: 40px; margin-bottom: 10px; }
.empty-text { font-size: 14px; font-weight: 600; color: var(--text-secondary); }
.empty-action { margin-top: 14px; max-width: 220px; margin-left: auto; margin-right: auto; }
</style>
