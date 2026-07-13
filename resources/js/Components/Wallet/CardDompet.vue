<template>
  <div class="card wallet-card" :class="{ 'is-archived': wallet.is_archived }" role="button" tabindex="0" @click="$emit('click', wallet)" @keydown.enter="$emit('click', wallet)">
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
        <div class="wallet-name-row">
          <span class="wallet-name">{{ wallet.display_name }}</span>
          <span v-if="wallet.is_primary" class="badge-primary">★ Utama</span>
          <span v-if="wallet.is_archived" class="badge-archived">Diarsipkan</span>
        </div>
        <div class="wallet-type">{{ wallet.is_saham ? 'Saham' : typeLabel(wallet.type) }}</div>
      </div>
      <div class="wallet-balance">
        <span v-if="!balanceHidden">{{ formatCurrency(wallet.balance, wallet.currency) }}</span>
        <span v-else class="hidden-text">••••••</span>
      </div>
    </div>

    <div class="wallet-actions" @click.stop>
      <button
        v-if="!wallet.is_primary && !wallet.is_archived"
        type="button"
        class="wa-btn"
        @click="$emit('set-primary', wallet)"
      >
        ⭐ Jadikan Utama
      </button>
      <button
        v-if="!wallet.is_archived"
        type="button"
        class="wa-btn"
        @click="$emit('archive', wallet)"
      >
        🗄️ Arsipkan
      </button>
      <button v-else type="button" class="wa-btn" @click="$emit('restore', wallet)">
        ♻️ Pulihkan
      </button>
      <button
        v-if="wallet.account_number"
        type="button"
        class="wa-btn"
        @click="copyAccountNumber"
      >
        {{ copied ? '✅ Tersalin' : '📋 Salin No. Rekening' }}
      </button>
    </div>
    <div v-if="$slots.actions" class="wallet-actions" @click.stop>
      <slot name="actions" />
    </div>
  </div>
</template>

<script setup>
import { ref } from 'vue'
import { formatCurrency } from '@/lib/format'
import { trackEvent } from '@/lib/analytics'

const props = defineProps({
  wallet: { type: Object, required: true },
  balanceHidden: { type: Boolean, default: false },
})
defineEmits(['click', 'set-primary', 'archive', 'restore'])

const typeLabel = (t) => ({ both: 'Multi Fungsi', cash_flow: 'Transaksi', saving: 'Tabungan' }[t] ?? t)

const copied = ref(false)

const copyAccountNumber = async () => {
  try {
    await navigator.clipboard.writeText(props.wallet.account_number)
    copied.value = true
    setTimeout(() => { copied.value = false }, 1500)
  } catch {
    /* clipboard tidak tersedia (mis. HTTP non-secure) — abaikan diam-diam */
  }
  trackEvent('dompet_copy_account_number', { wallet_id: props.wallet.id })
}
</script>

<style scoped>
.wallet-card { margin-bottom: 10px; cursor: pointer; min-height: 44px; }
.wallet-card:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
.wallet-card.is-archived { opacity: .65; }
.wallet-row { display: flex; align-items: center; gap: 12px; }
.wallet-logo { width: 44px; height: 44px; border-radius: 12px; color: white; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.wallet-logo-img { width: 100%; height: 100%; object-fit: cover; }
.wallet-info { flex: 1; min-width: 0; }
.wallet-name-row { display: flex; align-items: center; gap: 6px; flex-wrap: wrap; }
.wallet-name { font-size: 14px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.badge-primary { font-size: 10px; font-weight: 700; color: var(--amber); background: var(--amber-bg); padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
.badge-archived { font-size: 10px; font-weight: 700; color: var(--text-secondary); background: var(--background); padding: 2px 8px; border-radius: 99px; white-space: nowrap; }
.wallet-type { font-size: 11px; color: var(--text-secondary); }
.wallet-balance { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; flex-shrink: 0; }
.hidden-text { letter-spacing: .1em; color: var(--text-faint); }
.wallet-actions { margin-top: 10px; display: flex; gap: 6px; flex-wrap: wrap; }
.wa-btn { padding: 8px 10px; min-height: 36px; border-radius: var(--radius-sm); border: 1.5px solid var(--border); background: var(--surface); font-size: 11px; font-weight: 600; color: var(--text-secondary); cursor: pointer; white-space: nowrap; }
.wa-btn:hover { border-color: var(--primary); color: var(--primary); }
.wa-btn:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
</style>
