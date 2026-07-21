<template>
  <div class="card wallet-card" role="button" tabindex="0" @click="$emit('click', wallet)" @keydown.enter="$emit('click', wallet)">
    <div class="wallet-row">
      <div class="wallet-logo" :style="`background:${wallet.bank_color}`">
        <img
          v-if="wallet.logo_url"
          :src="wallet.logo_url"
          :alt="wallet.display_name"
          loading="lazy"
          class="wallet-logo-img"
        />
        <span v-else>{{ wallet.bank_initial }}</span>
      </div>
      <div class="wallet-info">
        <div class="wallet-name">{{ wallet.display_name }}</div>
        <div class="wallet-type">{{ wallet.is_saham ? 'Saham' : typeLabel(wallet.type) }}</div>
      </div>
      <div class="wallet-balance">
        <span v-if="!balanceHidden">{{ formatRupiah(wallet.balance) }}</span>
        <span v-else class="hidden-text">••••••</span>
      </div>
    </div>
    <div v-if="$slots.actions" class="wallet-actions" @click.stop>
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import { formatRupiah } from '@/lib/format'

defineProps({
  wallet: { type: Object, required: true },
  balanceHidden: { type: Boolean, default: false },
})
defineEmits(['click'])

const typeLabel = (t) => ({ both: 'Multi Fungsi', cash_flow: 'Transaksi', saving: 'Tabungan' }[t] ?? t)
</script>

<style scoped>
.wallet-card { margin-bottom: 10px; cursor: pointer; min-height: 44px; }
.wallet-card:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
.wallet-row { display: flex; align-items: center; gap: 12px; }
.wallet-logo { width: 44px; height: 44px; border-radius: 12px; color: white; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.wallet-logo-img { width: 100%; height: 100%; object-fit: cover; }
.wallet-info { flex: 1; min-width: 0; }
.wallet-name { font-size: 14px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wallet-type { font-size: 11px; color: var(--text-secondary); }
.wallet-balance { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; flex-shrink: 0; }
.hidden-text { letter-spacing: .1em; color: var(--text-faint); }
.wallet-actions { margin-top: 10px; display: flex; gap: 8px; }
</style>
