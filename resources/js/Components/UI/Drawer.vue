<template>
  <Teleport to="body">
    <div v-if="show" class="ui-drawer-overlay" @click.self="close" @keydown.esc="close">
      <div
        ref="panelRef"
        class="ui-drawer-panel"
        :class="`side-${side}`"
        role="dialog"
        aria-modal="true"
        :aria-labelledby="title ? titleId : undefined"
        tabindex="-1"
      >
        <div class="ui-drawer-handle"></div>
        <div class="ui-drawer-header">
          <h2 v-if="title" :id="titleId" class="ui-drawer-title">{{ title }}</h2>
          <button type="button" class="ui-drawer-close" aria-label="Tutup" @click="close">✕</button>
        </div>
        <div class="ui-drawer-body"><slot /></div>
        <div v-if="$slots.footer" class="ui-drawer-footer"><slot name="footer" /></div>
      </div>
    </div>
  </Teleport>
</template>

<script setup>
import { ref, watch, nextTick, onBeforeUnmount } from 'vue'

const props = defineProps({
  show: { type: Boolean, default: false },
  title: { type: String, default: '' },
  side: { type: String, default: 'right' }, // right (desktop) — collapses to bottom sheet on mobile via CSS
})
const emit = defineEmits(['close'])

const titleId = `ui-drawer-title-${Math.random().toString(36).slice(2, 9)}`
const panelRef = ref(null)
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
    panelRef.value?.focus()
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
.ui-drawer-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, .45);
  z-index: 300; backdrop-filter: blur(4px);
}
.ui-drawer-panel {
  background: var(--surface);
  position: fixed; left: 50%; bottom: -100%; transform: translateX(-50%);
  width: 100%; max-width: 480px;
  border-radius: 28px 28px 0 0;
  padding: 20px 20px 32px;
  max-height: 85vh; overflow-y: auto;
  box-shadow: 0 -10px 40px rgba(15, 23, 42, .15);
  transition: bottom .3s cubic-bezier(.4, 0, .2, 1);
}
.ui-drawer-overlay .ui-drawer-panel { bottom: 0; }
.ui-drawer-panel:focus-visible { outline: none; }
.ui-drawer-handle { width: 40px; height: 4px; background: var(--border); border-radius: 99px; margin: 0 auto 16px; }
.ui-drawer-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.ui-drawer-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 800; color: var(--text-primary); }
.ui-drawer-close { width: 32px; height: 32px; min-width: 44px; min-height: 44px; border: none; background: var(--background); border-radius: 50%; font-size: 14px; cursor: pointer; }
.ui-drawer-footer { display: flex; gap: 10px; margin-top: 18px; }

@media (min-width: 481px) {
  .ui-drawer-panel.side-right {
    left: auto; right: 24px; bottom: auto; top: 24px; transform: none;
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    max-height: calc(100vh - 48px);
  }
  .ui-drawer-handle { display: none; }
}
</style>
