<template>
  <div class="card wallet-card" role="button" tabindex="0" @click="$emit('click', wallet)" @keydown.enter="$emit('click', wallet)">
    <div class="wallet-row">
      <div class="wallet-logo" :style="logoStyle">
        <span v-if="wallet.icon" class="wallet-logo-emoji">{{ wallet.icon }}</span>
        <img
          v-else-if="wallet.logo_url"
          :src="wallet.logo_url"
          :alt="wallet.display_name"
          loading="lazy"
          class="wallet-logo-img"
        />
        <span v-else>{{ wallet.bank_initial }}</span>
      </div>
      <div class="wallet-info">
        <div class="wallet-name">{{ wallet.display_name }}</div>
        <div class="wallet-type">
          {{ wallet.is_saham ? 'Saham' : typeLabel(wallet.type) }}
          <span v-if="wallet.is_active === false" class="badge-archived">Diarsipkan</span>
        </div>
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
import { computed } from 'vue'
import { formatRupiah } from '@/lib/format'

const props = defineProps({
  wallet: { type: Object, required: true },
  balanceHidden: { type: Boolean, default: false },
})
defineEmits(['click'])

const typeLabel = (t) => ({ both: 'Multi Fungsi', cash_flow: 'Transaksi', saving: 'Tabungan' }[t] ?? t)

// wallet.color adalah token warna semantik (primary|success|danger|warning|info) — pakai
// CSS var, bukan hex baru. wallet.bank_color adalah warna brand bank pihak ketiga (data,
// bukan token desain), jadi tetap boleh dipakai langsung sebagai fallback.
const logoStyle = computed(() =>
  props.wallet.color ? { background: `var(--${props.wallet.color})` } : { background: props.wallet.bank_color }
)
</script>

<style scoped>
.wallet-card { margin-bottom: 10px; cursor: pointer; min-height: 44px; }
.wallet-card:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
.wallet-row { display: flex; align-items: center; gap: 12px; }
.wallet-logo { width: 44px; height: 44px; border-radius: 12px; color: white; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; font-size: 12px; display: flex; align-items: center; justify-content: center; flex-shrink: 0; overflow: hidden; }
.wallet-logo-img { width: 100%; height: 100%; object-fit: cover; }
.wallet-logo-emoji { font-size: 22px; line-height: 1; }
.wallet-info { flex: 1; min-width: 0; }
.wallet-name { font-size: 14px; font-weight: 700; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.wallet-type { font-size: 11px; color: var(--text-secondary); display: flex; align-items: center; gap: 6px; }
.wallet-balance { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; flex-shrink: 0; }
.hidden-text { letter-spacing: .1em; color: var(--text-faint); }
.wallet-actions { margin-top: 10px; display: flex; gap: 8px; }
.badge-archived { font-size: 10px; font-weight: 700; padding: 2px 8px; border-radius: 99px; color: var(--text-faint); border: 1px solid var(--border); }
</style>
