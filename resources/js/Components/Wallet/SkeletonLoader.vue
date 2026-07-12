<template>
  <div class="skeleton" :class="`skeleton-${variant}`" aria-hidden="true">
    <template v-if="variant === 'hero'">
      <div class="skeleton-line skeleton-line-lg"></div>
      <div class="skeleton-line skeleton-line-sm"></div>
      <div class="skeleton-line skeleton-line-xl"></div>
    </template>
    <template v-else-if="variant === 'card'">
      <div class="skeleton-block skeleton-avatar"></div>
      <div class="skeleton-lines">
        <div class="skeleton-line skeleton-line-md"></div>
        <div class="skeleton-line skeleton-line-sm"></div>
      </div>
    </template>
    <template v-else>
      <div class="skeleton-block skeleton-icon"></div>
      <div class="skeleton-lines">
        <div class="skeleton-line skeleton-line-md"></div>
        <div class="skeleton-line skeleton-line-sm"></div>
      </div>
      <div class="skeleton-block skeleton-amount"></div>
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
.skeleton { display: flex; align-items: center; gap: 12px; padding: 12px 0; }
.skeleton-hero { flex-direction: column; align-items: flex-start; gap: 10px; padding: 20px; }
.skeleton-card { padding: 16px; background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); }

.skeleton-block, .skeleton-line {
  background: linear-gradient(90deg, var(--border) 25%, var(--background) 50%, var(--border) 75%);
  background-size: 200% 100%;
  animation: skeleton-shimmer 1.4s ease-in-out infinite;
  border-radius: 8px;
}
.skeleton-avatar { width: 44px; height: 44px; border-radius: 12px; flex-shrink: 0; }
.skeleton-icon { width: 40px; height: 40px; border-radius: 12px; flex-shrink: 0; }
.skeleton-amount { width: 56px; height: 16px; flex-shrink: 0; }
.skeleton-lines { flex: 1; display: flex; flex-direction: column; gap: 8px; }
.skeleton-line { height: 12px; }
.skeleton-line-sm { width: 40%; }
.skeleton-line-md { width: 70%; }
.skeleton-line-lg { width: 50%; height: 14px; }
.skeleton-line-xl { width: 60%; height: 26px; }

@keyframes skeleton-shimmer {
  0% { background-position: 200% 0; }
  100% { background-position: -200% 0; }
}
</style>
