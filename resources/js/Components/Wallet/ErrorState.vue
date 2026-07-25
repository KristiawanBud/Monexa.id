<template>
  <div class="error-state" role="alert">
    <div class="error-illust">⚠️</div>
    <div class="error-text">{{ message }}</div>
    <button
      type="button"
      class="btn-secondary error-retry"
      @click="$emit('retry')"
    >
      {{ retryLabel }}
    </button>
  </div>
</template>

<script setup>
defineProps({
  message: { type: String, default: 'Gagal memuat data. Periksa koneksi internetmu.' },
  retryLabel: { type: String, default: 'Coba Lagi' },
})
defineEmits(['retry'])
</script>

<style scoped>
.error-state { text-align: center; padding: 32px 20px; }
.error-illust { font-size: 40px; margin-bottom: 10px; }
.error-text { font-size: 14px; font-weight: 600; color: var(--text-secondary); margin-bottom: 14px; }
.error-retry { max-width: 220px; margin: 0 auto; }
</style>
