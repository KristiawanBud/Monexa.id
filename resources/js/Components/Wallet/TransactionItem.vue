<template>
  <button type="button" class="tx-item" @click="$emit('click', transaction)">
    <div
      class="tx-icon"
      aria-hidden="true"
      :style="`background:${iconBg}`"
    >
      {{ icon }}
    </div>
    <div class="tx-info">
      <div class="tx-name">{{ transaction.note || transaction.category || (isTransfer ? 'Transfer' : 'Transaksi') }}</div>
      <div class="tx-cat">
        {{ [transaction.category, transaction.wallet, transaction.transacted_at_time].filter(Boolean).join(' · ') }}
      </div>
    </div>
    <div :class="['tx-amt', isTransfer ? 'neutral' : (transaction.type === 'income' ? 'up' : 'down')]">
      {{ isTransfer ? '' : (transaction.type === 'income' ? '+' : '−') }}{{ formatShort(transaction.amount) }}
    </div>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { formatShort } from '@/lib/format'

const props = defineProps({
  transaction: { type: Object, required: true },
})
defineEmits(['click'])

const isTransfer = computed(() => props.transaction.type === 'transfer')

const icon = computed(() => {
  if (isTransfer.value) return '🔄'
  return props.transaction.category_emoji || (props.transaction.type === 'income' ? '💵' : '🛍️')
})

const iconBg = computed(() => {
  if (isTransfer.value) return 'var(--background)'
  return props.transaction.type === 'income' ? 'var(--success-bg)' : 'var(--danger-bg)'
})
</script>

<style scoped>
.tx-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 4px;
  border-bottom: 1px solid var(--border);
  cursor: pointer;
  width: 100%;
  min-height: 44px;
  background: none;
  border-left: none;
  border-right: none;
  border-top: none;
  text-align: left;
  font-family: inherit;
  color: inherit;
}
.tx-item:last-child { border-bottom: none; }
.tx-item:active { opacity: .7; }
.tx-item:focus-visible { outline: none; box-shadow: var(--shadow-focus); border-radius: var(--radius-sm); }
.tx-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; }
.tx-info { flex: 1; min-width: 0; }
.tx-name { font-size: 13px; font-weight: 600; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tx-cat { font-size: 11px; color: var(--text-secondary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tx-amt { font-size: 13px; font-weight: 700; flex-shrink: 0; }
.tx-amt.up { color: var(--success); }
.tx-amt.down { color: var(--danger); }
.tx-amt.neutral { color: var(--text-secondary); }
</style>
