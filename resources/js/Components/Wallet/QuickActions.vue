<template>
  <div class="quick-actions">
    <button type="button" class="qa-btn income" @click="$emit('add-income')">
      💵 Tambah Pemasukan
    </button>
    <button type="button" class="qa-btn expense" @click="$emit('add-expense')">
      🔥 Tambah Pengeluaran
    </button>
    <button v-if="canTransfer" type="button" class="qa-btn transfer" @click="$emit('transfer')">
      🔄 Transfer Antar Dompet
    </button>
  </div>
</template>

<script setup>
defineProps({
  canTransfer: { type: Boolean, default: false },
})
defineEmits(['add-income', 'add-expense', 'transfer'])
</script>

<style scoped>
/* Mobile: toolbar eksplisit ini disembunyikan — FAB AppLayout sudah menjalankan fungsi
   yang sama (Tambah Pemasukan/Pengeluaran/dst) di layar kecil. */
.quick-actions { display: none; }

@media (min-width: 481px) {
  .quick-actions {
    display: flex;
    gap: 10px;
    flex-wrap: wrap;
    margin-bottom: 16px;
  }
  .qa-btn {
    flex: 1;
    min-width: 160px;
    min-height: 44px;
    padding: 12px 16px;
    border: none;
    border-radius: var(--radius-md);
    font-size: 13px;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
    cursor: pointer;
    transition: filter .15s;
  }
  .qa-btn:hover { filter: brightness(.97); }
  .qa-btn.income { background: var(--success-bg); color: var(--success); }
  .qa-btn.expense { background: var(--danger-bg); color: var(--danger); }
  .qa-btn.transfer { background: var(--primary-bg); color: var(--primary); }
}
</style>
