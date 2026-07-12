<template>
  <div class="progress-bar" role="progressbar" :aria-valuenow="clampedValue" aria-valuemin="0" aria-valuemax="100">
    <div class="progress-bar-fill" :style="fillStyle"></div>
  </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  value: { type: Number, required: true },
  colorVar: { type: String, default: '--primary' },
})

const clampedValue = computed(() => Math.min(100, Math.max(0, props.value)))
const fillStyle = computed(() => ({
  width: `${clampedValue.value}%`,
  background: `var(${props.colorVar})`,
}))
</script>

<style scoped>
.progress-bar { height: 6px; background: var(--background); border-radius: 99px; overflow: hidden; }
.progress-bar-fill { height: 100%; border-radius: 99px; transition: width .3s ease; }
</style>
