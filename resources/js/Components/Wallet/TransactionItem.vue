<template>
  <div v-if="isTransfer" class="tx-item tx-item-transfer">
    <div class="tx-icon tx-icon-transfer">
      {{ transaction.type === 'transfer_out' ? '↗' : '↙' }}
    </div>
    <div class="tx-info">
      <div class="tx-name">
        <span class="tx-transfer-badge">Transfer</span>
        {{ transaction.type === 'transfer_out' ? 'ke' : 'dari' }} {{ transaction.counterparty_wallet }}
      </div>
      <div class="tx-cat">
        {{ [transaction.wallet, transaction.transacted_at_time].filter(Boolean).join(' · ') }}
      </div>
    </div>
    <div class="tx-amt tx-amt-neutral">
      {{ transaction.type === 'transfer_out' ? '−' : '+' }}{{ formatShort(transaction.amount) }}
    </div>
    <button
      v-if="transaction.type === 'transfer_out'"
      type="button"
      class="tx-cancel-btn"
      aria-label="Batalkan Transfer"
      @click="$emit('cancel-transfer', transaction)"
    >
      Batalkan
    </button>
  </div>

  <button v-else type="button" class="tx-item" @click="$emit('click', transaction)">
    <div
      class="tx-icon"
      :style="`background:${transaction.type === 'income' ? 'var(--success-bg)' : 'var(--danger-bg)'}`"
    >
      {{ transaction.category_emoji || (transaction.type === 'income' ? '💵' : '🛍️') }}
    </div>
    <div class="tx-info">
      <div class="tx-name">{{ transaction.note || transaction.category || 'Transaksi' }}</div>
      <div class="tx-cat">
        {{ [transaction.category, transaction.wallet, transaction.transacted_at_time].filter(Boolean).join(' · ') }}
      </div>
    </div>
    <div :class="['tx-amt', transaction.type === 'income' ? 'up' : 'down']">
      {{ transaction.type === 'income' ? '+' : '−' }}{{ formatShort(transaction.amount) }}
    </div>
  </button>
</template>

<script setup>
import { computed } from 'vue'
import { formatShort } from '@/lib/format'

const props = defineProps({
  transaction: { type: Object, required: true },
})
defineEmits(['click', 'cancel-transfer'])

const isTransfer = computed(() => props.transaction.type === 'transfer_in' || props.transaction.type === 'transfer_out')
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
.tx-amt-neutral { color: var(--text-secondary); }

.tx-item-transfer { cursor: default; }
.tx-icon-transfer { background: var(--background); color: var(--text-secondary); }
.tx-transfer-badge {
  display: inline-block; font-size: 10px; font-weight: 700; text-transform: uppercase;
  letter-spacing: .04em; color: var(--text-secondary); background: var(--background);
  border-radius: 6px; padding: 1px 6px; margin-right: 6px; vertical-align: middle;
}
.tx-cancel-btn {
  flex-shrink: 0; font-size: 11px; font-weight: 700; color: var(--danger);
  background: var(--danger-bg); border: none; border-radius: var(--radius-sm);
  padding: 8px 10px; min-height: 36px; cursor: pointer;
}
</style>
