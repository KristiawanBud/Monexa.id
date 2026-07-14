<template>
  <div class="tx-date-group">
    <h3 class="tx-date-heading">{{ label }}</h3>
    <div class="tx-date-list">
      <TransactionItem
        v-for="t in transactions"
        :key="t.id"
        :transaction="t"
        @click="$emit('item-click', t)"
        @cancel-transfer="$emit('cancel-transfer', t)"
      />
    </div>
  </div>
</template>

<script setup>
import TransactionItem from './TransactionItem.vue'

defineProps({
  label: { type: String, required: true },
  transactions: { type: Array, required: true },
})
defineEmits(['item-click', 'cancel-transfer'])
</script>

<style scoped>
.tx-date-group { margin-bottom: 8px; }
.tx-date-heading {
  font-size: 12px;
  font-weight: 700;
  color: var(--text-secondary);
  text-transform: uppercase;
  letter-spacing: .04em;
  padding: 10px 4px 6px;
}
</style>
