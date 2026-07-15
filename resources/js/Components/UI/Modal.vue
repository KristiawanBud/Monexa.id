<template>
  <Teleport to="body">
    <div v-if="show" class="ui-modal-overlay" @click.self="close" @keydown.esc="close">
      <div
        ref="dialogRef"
        class="ui-modal-sheet"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="title ? titleId : undefined"
        tabindex="-1"
      >
        <div class="ui-modal-handle"></div>
        <div v-if="title" :id="titleId" class="ui-modal-title">{{ title }}</div>
        <div class="ui-modal-body"><slot /></div>
        <div v-if="$slots.footer" class="ui-modal-footer"><slot name="footer" /></div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
})
const emit = defineEmits(['close'])

const titleId = `ui-modal-title-${Math.random().toString(36).slice(2, 9)}`
const dialogRef = ref(null)
let previouslyFocused = null

function close() {
  emit('close')
}

function onKeydown(e) {
  if (e.key === 'Escape') close()
}

watch(() => props.show, async (visible) => {
  if (visible) {
    previouslyFocused = document.activeElement
    document.addEventListener('keydown', onKeydown)
    await nextTick()
    dialogRef.value?.focus()
  } else {
    document.removeEventListener('keydown', onKeydown)
    previouslyFocused?.focus?.()
  }
})

onBeforeUnmount(() => {
  document.removeEventListener('keydown', onKeydown)
})
</script>

<style scoped>
.ui-modal-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, .45);
  z-index: 600; display: flex; align-items: flex-end; justify-content: center;
  backdrop-filter: blur(4px);
}
.ui-modal-sheet {
  background: var(--surface); border-radius: 28px 28px 0 0;
  width: 100%; max-width: 480px; max-height: 88vh;
  padding: 20px 20px 30px; overflow-y: auto;
  box-shadow: var(--shadow-lg);
}
.ui-modal-sheet:focus-visible { outline: none; }
.ui-modal-handle { width: 40px; height: 4px; background: var(--border); border-radius: 99px; margin: 0 auto 16px; }
.ui-modal-title {
  font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 800;
  color: var(--text-primary); margin-bottom: 16px; text-align: center;
}
.ui-modal-footer { display: flex; gap: 10px; margin-top: 18px; }

@media (min-width: 640px) {
  .ui-modal-overlay { align-items: center; }
  .ui-modal-sheet { border-radius: var(--radius-lg); }
}
</style>
