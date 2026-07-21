<template>
  <div class="skeleton" :class="`skeleton-${variant}`" aria-hidden="true">
    <template v-if="variant === 'hero'">
      <div class="sk-block sk-hero-title"></div>
      <div class="sk-block sk-hero-amount"></div>
    </template>
    <template v-else-if="variant === 'card'">
      <div class="sk-block sk-card-line" style="width:60%"></div>
      <div class="sk-block sk-card-line" style="width:40%"></div>
    </template>
    <template v-else>
      <div class="sk-block sk-avatar"></div>
      <div class="sk-lines">
        <div class="sk-block sk-line" style="width:70%"></div>
        <div class="sk-block sk-line" style="width:40%"></div>
      </div>
      <div class="sk-block sk-amount"></div>
    </template>
  </div>
</template>

<script setup>
defineProps({
  variant: {
    type: String,
    default: 'list-item',
    validator: (v) => ['card', 'list-item', 'hero'].includes(v),
  },
})
</script>

<style scoped>
.sk-block {
  background: linear-gradient(90deg, var(--border) 25%, var(--background) 50%, var(--border) 75%);
  background-size: 200% 100%;
  animation: sk-shimmer 1.4s ease-in-out infinite;
  border-radius: 8px;
}
@keyframes sk-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}

.skeleton-hero { padding: 8px 0; }
.sk-hero-title { height: 18px; width: 40%; margin-bottom: 14px; }
.sk-hero-amount { height: 30px; width: 65%; }

.skeleton-card { padding: 4px 0; }
.sk-card-line { height: 12px; margin-bottom: 8px; }

.skeleton-list-item { display: flex; align-items: center; gap: 12px; padding: 12px 0; }
.sk-avatar { width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0; }
.sk-lines { flex: 1; min-width: 0; }
.sk-line { height: 10px; margin-bottom: 8px; }
.sk-line:last-child { margin-bottom: 0; }
.sk-amount { width: 56px; height: 12px; flex-shrink: 0; }
</style>
