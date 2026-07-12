<template>
  <button type="button" class="tx-item" @click="$emit('click', transaction)">
    <span
      class="tx-item-icon"
      :style="{ background: transaction.type === 'income' ? 'var(--success-bg)' : 'var(--danger-bg)' }"
    >
      <img
        v-if="transaction.category_icon_url"
        :src="transaction.category_icon_url"
        :alt="transaction.category || ''"
        class="tx-item-icon-img"
        loading="lazy"
      />
      <template v-else>{{ transaction.category_emoji || (transaction.type === 'income' ? '💵' : '🛍️') }}</template>
    </span>
    <span class="tx-item-info">
      <span class="tx-item-name">{{ transaction.note || transaction.category || 'Transaksi' }}</span>
      <span class="tx-item-meta">{{ metaLabel }}</span>
    </span>
    <span class="tx-item-amount" :class="transaction.type === 'income' ? 'up' : 'down'">
      {{ transaction.type === 'income' ? '+' : '−' }}{{ formatShort(transaction.amount) }}
    </span>
  </button>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
  transaction: { type: Object, required: true },
})
defineEmits(['click'])

const metaLabel = computed(() =>
  [props.transaction.category, props.transaction.wallet, props.transaction.transacted_at_label]
    .filter(Boolean)
    .join(' · ')
)

const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000) return (n / 1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000) return (n / 1_000).toFixed(0) + 'rb'
  return String(n)
}
</script>

<style scoped>
.tx-item { display: flex; align-items: center; gap: 12px; padding: 12px 4px; border: none; background: none; width: 100%; text-align: left; cursor: pointer; border-bottom: 1px solid var(--border); border-radius: var(--radius-sm); font-family: inherit; min-height: 44px; }
.tx-item:last-child { border-bottom: none; }
.tx-item:active { opacity: .7; }
.tx-item:focus-visible { box-shadow: var(--shadow-focus); outline: none; }
.tx-item-icon { width: 40px; height: 40px; border-radius: 12px; display: flex; align-items: center; justify-content: center; font-size: 18px; flex-shrink: 0; overflow: hidden; }
.tx-item-icon-img { width: 100%; height: 100%; object-fit: cover; }
.tx-item-info { flex: 1; min-width: 0; display: flex; flex-direction: column; }
.tx-item-name { font-size: 13px; font-weight: 600; color: var(--text-primary); white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.tx-item-meta { font-size: 11px; color: var(--text-secondary); }
.tx-item-amount { font-size: 13px; font-weight: 700; flex-shrink: 0; }
.tx-item-amount.up { color: var(--success); }
.tx-item-amount.down { color: var(--danger); }
</style>
