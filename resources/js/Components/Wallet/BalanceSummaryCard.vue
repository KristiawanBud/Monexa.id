<template>
  <section class="balance-summary" aria-labelledby="balance-summary-heading">
    <div class="dompet-hero-bg">
      <div class="hero-top-row">
        <div>
          <h1 id="balance-summary-heading" class="hero-page-title">Dompet 👛</h1>
          <div class="hero-page-sub">Kelola semua rekening dan uangmu di sini.</div>
        </div>
        <button class="hero-add-btn" aria-label="Tambah" @click="$emit('add')">＋</button>
      </div>

      <AppIcon slug="dompet_hero" class="dompet-hero-illustration">👛</AppIcon>

      <div class="hero-saldo-row">
        <span class="hero-saldo-label">TOTAL SALDO</span>
        <button
          class="hero-eye-btn"
          :aria-label="balanceHidden ? 'Tampilkan saldo' : 'Sembunyikan saldo'"
          @click="$emit('update:balanceHidden', !balanceHidden)"
        >
          <svg v-if="balanceHidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M3 3l18 18M10.58 10.58a2 2 0 002.83 2.83M9.88 5.09A9.77 9.77 0 0112 5c5 0 9 4 10 7-.36 1.1-1 2.19-1.87 3.19M6.1 6.1C4.2 7.4 2.8 9.4 2 12c1.14 3.5 5.05 7 10 7 1.52 0 2.96-.34 4.24-.94" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
          </svg>
          <svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
            <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
          </svg>
        </button>
      </div>
      <div class="hero-saldo-amount">
        <span v-if="!balanceHidden">{{ formatRupiah(totalBalance) }}</span>
        <span v-else class="hidden-text">••••••••••</span>
      </div>
      <div class="hero-wallet-badge">● {{ activeWalletsCount }} Dompet Aktif</div>

      <div v-if="showRangeStats" class="hero-range-stats">
        <div class="hrs-item">
          <span class="hrs-label">↓ Masuk</span>
          <span class="hrs-val up">{{ formatShort(totalIncome) }}</span>
        </div>
        <div class="hrs-item">
          <span class="hrs-label">↑ Keluar</span>
          <span class="hrs-val down">{{ formatShort(totalExpense) }}</span>
        </div>
        <div class="hrs-item">
          <span class="hrs-label">{{ rangeLabel }}</span>
        </div>
      </div>
    </div>

    <div class="card breakdown-card">
      <div class="breakdown-item">
        <div class="bd-icon cash">💵</div>
        <div class="bd-info">
          <div class="bd-label">Saldo Cash</div>
          <div class="bd-value cash">{{ formatShort(cashTotal) }}</div>
          <div class="bd-bar-bg"><div class="bd-bar-fill cash" :style="`width:${barWidth(cashTotal)}%`"></div></div>
        </div>
      </div>
      <div class="breakdown-item">
        <div class="bd-icon bank">🏦</div>
        <div class="bd-info">
          <div class="bd-label">Saldo Bank</div>
          <div class="bd-value bank">{{ formatShort(bankTotal) }}</div>
          <div class="bd-bar-bg"><div class="bd-bar-fill bank" :style="`width:${barWidth(bankTotal)}%`"></div></div>
        </div>
      </div>
      <div class="breakdown-item">
        <div class="bd-icon ewallet">👛</div>
        <div class="bd-info">
          <div class="bd-label">E-Wallet</div>
          <div class="bd-value ewallet">{{ formatShort(ewalletTotal) }}</div>
          <div class="bd-bar-bg"><div class="bd-bar-fill ewallet" :style="`width:${barWidth(ewalletTotal)}%`"></div></div>
        </div>
      </div>
    </div>
  </section>
</template>

<script setup>
import AppIcon from '@/Components/AppIcon.vue'
import { formatRupiah, formatShort } from '@/lib/format'

const props = defineProps({
  totalBalance: { type: Number, default: 0 },
  activeWalletsCount: { type: Number, default: 0 },
  cashTotal: { type: Number, default: 0 },
  bankTotal: { type: Number, default: 0 },
  ewalletTotal: { type: Number, default: 0 },
  balanceHidden: { type: Boolean, default: false },
  showRangeStats: { type: Boolean, default: false },
  totalIncome: { type: Number, default: 0 },
  totalExpense: { type: Number, default: 0 },
  rangeLabel: { type: String, default: '' },
})
defineEmits(['update:balanceHidden', 'add'])

const barWidth = (value) => (props.totalBalance ? Math.min(100, (value / props.totalBalance) * 100) : 0)
</script>

<style scoped>
.dompet-hero-bg {
  position: relative; overflow: hidden;
  background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dark) 100%);
  margin: -20px -20px 0; padding: 20px 20px 24px;
  border-radius: 0 0 26px 26px;
}
.hero-top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; position: relative; z-index: 2; }
.hero-page-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 22px; font-weight: 800; color: white; }
.hero-page-sub { font-size: 12px; color: rgba(255,255,255,.75); margin-top: 4px; }
.hero-add-btn { width: 44px; height: 44px; border-radius: 50%; background: white; color: var(--primary); border: none; font-size: 20px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.15); flex-shrink: 0; }
.dompet-hero-illustration { position: absolute; right: 14px; top: 50px; width: 80px; height: 80px; opacity: .95; pointer-events: none; z-index: 1; }
.hero-saldo-row { display: flex; align-items: center; gap: 8px; position: relative; z-index: 2; }
.hero-saldo-label { font-size: 11px; font-weight: 700; letter-spacing: .06em; color: rgba(255,255,255,.8); }
.hero-eye-btn { background: rgba(255,255,255,.18); border: none; border-radius: 50%; width: 44px; height: 44px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; }
.hero-eye-btn svg { width: 14px; height: 14px; }
.hero-saldo-amount { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 28px; font-weight: 800; color: white; margin: 4px 0 10px; position: relative; z-index: 2; }
.hidden-text { letter-spacing: .1em; color: rgba(255,255,255,.6); }
.hero-wallet-badge { display: inline-block; background: rgba(255,255,255,.18); color: white; font-size: 11px; font-weight: 600; padding: 5px 12px; border-radius: 99px; position: relative; z-index: 2; }

.hero-range-stats { display: flex; gap: 18px; margin-top: 16px; position: relative; z-index: 2; }
.hrs-item { display: flex; flex-direction: column; gap: 2px; }
.hrs-label { font-size: 10px; color: rgba(255,255,255,.75); font-weight: 600; }
.hrs-val { font-size: 14px; font-weight: 800; color: white; }
.hrs-val.up, .hrs-val.down { color: white; }

.breakdown-card { display: flex; gap: 14px; margin: -14px 0 16px; padding: 16px; position: relative; z-index: 3; }
.breakdown-item { flex: 1; display: flex; gap: 8px; align-items: flex-start; min-width: 0; }
.bd-icon { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.bd-icon.cash { background: var(--success-bg); }
.bd-icon.bank { background: var(--primary-bg); }
.bd-icon.ewallet { background: var(--ewallet-bg); }
.bd-info { flex: 1; min-width: 0; }
.bd-label { font-size: 10px; color: var(--text-secondary); font-weight: 600; }
.bd-value { font-size: 13px; font-weight: 800; margin: 2px 0 4px; }
.bd-value.cash { color: var(--success); }
.bd-value.bank { color: var(--primary); }
.bd-value.ewallet { color: var(--ewallet); }
.bd-bar-bg { height: 4px; background: var(--background); border-radius: 99px; overflow: hidden; }
.bd-bar-fill { height: 100%; border-radius: 99px; }
.bd-bar-fill.cash { background: var(--success); }
.bd-bar-fill.bank { background: var(--primary); }
.bd-bar-fill.ewallet { background: var(--ewallet); }

@media (min-width: 768px) {
  .breakdown-card { max-width: 480px; }
}
</style>
