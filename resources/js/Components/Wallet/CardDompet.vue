<template>
  <div class="card-dompet">
    <div class="cd-row">
      <div class="cd-logo" :style="{ background: wallet.bank_color }">
        <img
          v-if="wallet.logo_url"
          :src="wallet.logo_url"
          :alt="wallet.bank_name || wallet.display_name"
          class="cd-logo-img"
          loading="lazy"
        />
        <span v-else>{{ wallet.bank_initial }}</span>
      </div>
      <div class="cd-info">
        <div class="cd-name">{{ wallet.display_name }}</div>
        <div class="cd-type">{{ wallet.is_saham ? 'Saham' : typeLabel(wallet.type) }}</div>
      </div>
      <div class="cd-balance">
        <span v-if="!balanceHidden">{{ formatRupiah(wallet.balance) }}</span>
        <span v-else class="cd-balance-hidden">••••••••</span>
      </div>
    </div>

    <ProgressBar v-if="usagePercent !== null && usagePercent !== undefined" :value="usagePercent" class="cd-progress" />

    <div v-if="$slots.actions" class="cd-actions">
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import ProgressBar from './ProgressBar.vue'

defineProps({
  wallet: { type: Object, required: true },
  balanceHidden: { type: Boolean, default: false },
  usagePercent: { default: null },
})

const typeLabel = (t) => ({ both: 'Multi Fungsi', cash_flow: 'Transaksi', saving: 'Tabungan', investment: 'Investasi' }[t] ?? t)
const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
</script>

<style scoped>
.card-dompet { background: var(--surface); border-radius: var(--radius-lg); box-shadow: var(--shadow-card); padding: 16px; }
.cd-row { display: flex; align-items: center; gap: 12px; }
.cd-logo { width: 44px; height: 44px; border-radius: 12px; color: white; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.cd-logo-img { width: 100%; height: 100%; object-fit: cover; }
.cd-info { flex: 1; min-width: 0; }
.cd-name { font-size: 14px; font-weight: 700; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
.cd-type { font-size: 11px; color: var(--text-secondary); }
.cd-balance { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; flex-shrink: 0; text-align: right; }
.cd-balance-hidden { letter-spacing: .1em; color: var(--text-faint); }
.cd-progress { margin-top: 12px; }
.cd-actions { display: flex; gap: 8px; margin-top: 12px; }
</style>
