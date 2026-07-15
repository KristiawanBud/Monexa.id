<template>
  <Teleport to="body">
    <div class="ui-toast-stack" aria-live="polite">
      <TransitionGroup name="ui-toast-fade">
        <Toast
          v-for="t in toasts"
          :key="t.id"
          :variant="t.variant"
          :message="t.message"
          @dismiss="dismiss(t.id)"
        />
      </TransitionGroup>
    </div>
  </Teleport>
</template>

<script setup>
import { useToast } from '@/Composables/useToast'
import Toast from './Toast.vue'

const { toasts, dismiss } = useToast()
</script>

<style scoped>
.ui-toast-stack {
  position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
  z-index: 9999; display: flex; flex-direction: column; gap: 8px; align-items: center;
  pointer-events: none;
}
.ui-toast-fade-enter-active, .ui-toast-fade-leave-active { transition: all .3s ease; }
.ui-toast-fade-enter-from, .ui-toast-fade-leave-to { opacity: 0; transform: translateY(-12px); }
</style>
