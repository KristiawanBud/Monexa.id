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

      <AppIcon slug="dompet_hero" class="dompet-hero-illustration" aria-hidden="true">👛</AppIcon>

      <template v-if="loading">
        <SkeletonLoader variant="hero" class="hero-skeleton" />
      </template>
      <template v-else>
        <div class="hero-saldo-row">
          <span class="hero-saldo-label">TOTAL SALDO</span>
          <button
            class="hero-eye-btn"
            :aria-label="balanceHidden ? 'Tampilkan saldo' : 'Sembunyikan saldo'"
            @click="$emit('update:balanceHidden', !balanceHidden)"
          >
            <svg v-if="balanceHidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
              <path d="M3 3l18 18M10.58 10.58a2 2 0 002.83 2.83M9.88 5.09A9.77 9.77 0 0112 5c5 0 9 4 10 7-.36 1.1-1 2.19-1.87 3.19M6.1 6.1C4.2 7.4 2.8 9.4 2 12c1.14 3.5 5.05 7 10 7 1.52 0 2.96-.34 4.24-.94" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
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
      </template>
    </div>

    <div v-if="loading" class="card breakdown-card">
      <SkeletonLoader v-for="n in 3" :key="n" variant="card" class="breakdown-skeleton-item" />
    </div>
    <div v-else class="card breakdown-card" role="group" aria-label="Filter kelompok saldo">
      <button
        v-for="item in breakdownItems"
        :key="item.key"
        type="button"
        :class="['breakdown-item', { active: activeBalanceGroup === item.key }]"
        :aria-pressed="activeBalanceGroup === item.key"
        :aria-label="`Filter transaksi kelompok saldo ${item.label}${activeBalanceGroup === item.key ? ' (aktif)' : ''}`"
        @click="$emit('select-group', item.key)"
      >
        <div class="bd-icon" :class="item.key" aria-hidden="true">{{ item.icon }}</div>
        <div class="bd-info">
          <div class="bd-label">{{ item.label }}</div>
          <div class="bd-value" :class="item.key">{{ formatShort(item.value) }}</div>
          <div class="bd-bar-bg"><div class="bd-bar-fill" :class="item.key" :style="`width:${barWidth(item.value)}%`"></div></div>
        </div>
      </button>
    </div>
  </section>
</template>

<script setup>
import { computed } from 'vue'
import AppIcon from '@/Components/AppIcon.vue'
import SkeletonLoader from '@/Components/Wallet/SkeletonLoader.vue'
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
  activeBalanceGroup: { type: String, default: null },
  loading: { type: Boolean, default: false },
})
defineEmits(['update:balanceHidden', 'add', 'select-group'])

const breakdownItems = computed(() => [
  { key: 'cash', label: 'Saldo Cash', icon: '💵', value: props.cashTotal },
  { key: 'bank', label: 'Saldo Bank', icon: '🏦', value: props.bankTotal },
  { key: 'ewallet', label: 'E-Wallet', icon: '👛', value: props.ewalletTotal },
])

const barWidth = (value) => (props.totalBalance ? Math.min(100, (value / props.totalBalance) * 100) : 0)
</script>

<style scoped>
.dompet-hero-bg {
  position: relative; overflow: hidden;
  background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dark) 100%);
  margin: -20px -20px 0; padding: 20px 20px 24px;
  border-radius: 0 0 26px 26px;
  min-height: clamp(220px, 58vw, 260px);
  display: flex; flex-direction: column; justify-content: center;
}
.hero-top-row { display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 14px; position: relative; z-index: 2; }
.hero-page-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 22px; font-weight: 800; color: white; }
.hero-page-sub { font-size: 12px; color: rgba(255,255,255,.75); margin-top: 4px; }
.hero-add-btn { width: 44px; height: 44px; border-radius: 50%; background: white; color: var(--primary); border: none; font-size: 20px; cursor: pointer; box-shadow: 0 4px 12px rgba(0,0,0,.15); flex-shrink: 0; }
.dompet-hero-illustration { position: absolute; right: 14px; top: 50px; width: 80px; height: 80px; opacity: .95; pointer-events: none; z-index: 1; }
.hero-saldo-row { display: flex; align-items: center; gap: 8px; position: relative; z-index: 2; }
.hero-saldo-label { font-size: 12px; font-weight: 700; letter-spacing: .06em; color: rgba(255,255,255,.8); }
.hero-eye-btn { background: rgba(255,255,255,.18); border: none; border-radius: 50%; width: 44px; height: 44px; cursor: pointer; display: flex; align-items: center; justify-content: center; color: white; }
.hero-eye-btn svg { width: 14px; height: 14px; }
.hero-saldo-amount { font-family: 'Plus Jakarta Sans', sans-serif; font-size: clamp(28px, 8vw, 32px); font-weight: 800; color: white; margin: 4px 0 10px; position: relative; z-index: 2; line-height: 1.2; }
.hidden-text { letter-spacing: .1em; color: rgba(255,255,255,.6); }
.hero-wallet-badge { display: inline-block; background: rgba(255,255,255,.18); color: white; font-size: 12px; font-weight: 600; padding: 5px 12px; border-radius: 99px; position: relative; z-index: 2; }

.hero-skeleton { position: relative; z-index: 2; }
.hero-skeleton :deep(.sk-block) { background: linear-gradient(90deg, rgba(255,255,255,.25) 25%, rgba(255,255,255,.4) 50%, rgba(255,255,255,.25) 75%); background-size: 200% 100%; }

.hero-range-stats { display: flex; gap: 18px; margin-top: 16px; position: relative; z-index: 2; }
.hrs-item { display: flex; flex-direction: column; gap: 2px; }
.hrs-label { font-size: 10px; color: rgba(255,255,255,.75); font-weight: 600; }
.hrs-val { font-size: 14px; font-weight: 800; color: white; }
.hrs-val.up, .hrs-val.down { color: white; }

.breakdown-card { display: flex; gap: 10px; margin: -14px 0 16px; padding: 16px; position: relative; z-index: 3; }
.breakdown-item {
  flex: 1; display: flex; gap: 8px; align-items: flex-start; min-width: 0;
  border: 1.5px solid transparent; border-radius: 14px; padding: 6px;
  background: none; cursor: pointer; text-align: left; font-family: inherit; color: inherit;
  transition: border-color .15s, background .15s;
  min-height: 44px;
}
.breakdown-item:focus-visible { outline: none; box-shadow: var(--shadow-focus); }
.breakdown-item.active { border-color: var(--primary); background: var(--background); }
.breakdown-skeleton-item { flex: 1; min-height: 56px; }
.bd-icon { width: 32px; height: 32px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 15px; flex-shrink: 0; }
.bd-icon.cash { background: var(--success-bg); }
.bd-icon.bank { background: var(--primary-bg); }
.bd-icon.ewallet { background: var(--amber-bg); }
.bd-info { flex: 1; min-width: 0; }
.bd-label { font-size: 10px; color: var(--text-secondary); font-weight: 600; }
.bd-value { font-size: 13px; font-weight: 800; margin: 2px 0 4px; }
.bd-value.cash { color: var(--success); }
.bd-value.bank { color: var(--primary); }
.bd-value.ewallet { color: var(--amber); }
.bd-bar-bg { height: 4px; background: var(--background); border-radius: 99px; overflow: hidden; }
.bd-bar-fill { height: 100%; border-radius: 99px; }
.bd-bar-fill.cash { background: var(--success); }
.bd-bar-fill.bank { background: var(--primary); }
.bd-bar-fill.ewallet { background: var(--amber); }

@media (max-width: 359px) {
  .breakdown-card {
    overflow-x: auto;
    scroll-snap-type: x mandatory;
    -webkit-overflow-scrolling: touch;
    scrollbar-width: none;
  }
  .breakdown-card::-webkit-scrollbar { display: none; }
  .breakdown-item, .breakdown-skeleton-item { flex: 0 0 82%; scroll-snap-align: start; }
}

@media (min-width: 768px) {
  .breakdown-card { max-width: 480px; }
}
</style>
