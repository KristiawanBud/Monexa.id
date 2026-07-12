<template>
  <div class="error-state">
    <div class="error-state-icon">⚠️</div>
    <div class="error-state-message">{{ message }}</div>
    <button type="button" class="btn-secondary error-state-retry" @click="$emit('retry')">
      {{ retryLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, required: true },
  retryLabel: { type: String, default: 'Coba Lagi' },
})
defineEmits(['retry'])
</script>

<style scoped>
.error-state { text-align: center; padding: 32px 20px; }
.error-state-icon { font-size: 40px; margin-bottom: 10px; }
.error-state-message { font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 14px; }
.error-state-retry { max-width: 220px; margin: 0 auto; }
</style>
