<template>
  <AppLayout>
    <div class="dash-hero-bg">
      <div class="dash-header">
        <div class="greeting-block">
          <div class="greeting-label">👋 Selamat {{ greetingTime }},</div>
          <div class="greeting-name">{{ firstName }}</div>
          <div class="greeting-sub">Semoga keuanganmu hari ini sehat selalu!</div>
        </div>
        <div class="header-actions">
          <button class="icon-btn-round" @click="showNotif = true">
            🔔
            <span v-if="notifications.length > 0" class="notif-badge">{{ notifications.length }}</span>
          </button>
          <Link :href="route('account')" class="avatar-btn">{{ initials }}</Link>
        </div>
      </div>

      <div class="hero-card">
        <AppIcon slug="dashboard_hero" class="hero-illustration">💼</AppIcon>

        <div class="hero-top">
          <div class="hero-label">TOTAL ASET BERSIH</div>
          <button class="hide-btn" @click="toggleBalance" :aria-label="balanceHidden ? 'Tampilkan saldo' : 'Sembunyikan saldo'">
            <svg v-if="balanceHidden" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M3 3l18 18M10.58 10.58a2 2 0 002.83 2.83M9.88 5.09A9.77 9.77 0 0112 5c5 0 9 4 10 7-.36 1.1-1 2.19-1.87 3.19M6.1 6.1C4.2 7.4 2.8 9.4 2 12c1.14 3.5 5.05 7 10 7 1.52 0 2.96-.34 4.24-.94" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
            </svg>
            <svg v-else viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg">
              <path d="M2 12s4-7 10-7 10 7 10 7-4 7-10 7-10-7-10-7z" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
              <circle cx="12" cy="12" r="3" stroke="currentColor" stroke-width="1.8"/>
            </svg>
          </button>
        </div>

        <div class="hero-amount">
          <span v-if="!balanceHidden">{{ formatRupiah(totalBalance) }}</span>
          <span v-else class="hidden-text">••••••••••</span>
        </div>

        <div class="hero-compare" v-if="!balanceHidden">
          <span :class="['trend', incomeTrendUp ? 'up' : 'down']">{{ incomeTrendUp ? '↑' : '↓' }} {{ formatShort(totalIncome) }}</span>
          <span class="sep">|</span>
          <span :class="['trend', expenseTrendUp ? 'down' : 'up']">{{ expenseTrendUp ? '↓' : '↑' }} {{ formatShort(totalExpense) }}</span>
          <span class="vs-label">vs {{ prevMonthLabel }}</span>
        </div>

        <div class="hero-progress" v-if="totalBudget > 0">
          <div class="progress-label-row">
            <span>PROGRESS BULAN INI</span>
            <span class="progress-pct">{{ budgetPct ?? 0 }}%</span>
          </div>
          <div class="progress-bar-bg">
            <div class="progress-bar-fill" :style="`width:${Math.min(budgetPct ?? 0, 100)}%`"></div>
          </div>
          <div class="progress-sub">Budget digunakan: Rp {{ formatShort(totalExpense) }} / Rp {{ formatShort(totalBudget) }}</div>
        </div>
      </div>
    </div>

    <div class="page-content">

      <!-- Quick Actions -->
      <div class="qa-row">
        <Link :href="route('dompet.index')" :data="{ tab: 'in' }" class="qa-btn">
          <div class="qa-icon pemasukan"><AppIcon slug="qa_pemasukan">💵</AppIcon></div>
          <span>Pemasukan</span>
        </Link>
        <Link :href="route('dompet.index')" :data="{ tab: 'out' }" class="qa-btn">
          <div class="qa-icon pengeluaran"><AppIcon slug="qa_pengeluaran">🔥</AppIcon></div>
          <span>Pengeluaran</span>
        </Link>
        <Link :href="route('receipt.index')" class="qa-btn">
          <div class="qa-icon scan"><AppIcon slug="qa_scan">📷</AppIcon></div>
          <span>Scan Struk</span>
        </Link>
        <Link :href="route('asset.index')" class="qa-btn">
          <div class="qa-icon aset"><AppIcon slug="qa_aset">💎</AppIcon></div>
          <span>Aset</span>
        </Link>
      </div>

      <!-- Ringkasan Hari Ini -->
      <div class="section">
        <div class="card today-card">
          <div class="tc-head">
            <span class="section-title">RINGKASAN HARI INI</span>
            <Link :href="route('dompet.index')" class="see-all">Lihat semua →</Link>
          </div>
          <div class="tc-grid">
            <div class="tc-item">
              <div class="tc-icon up-bg">📈</div>
              <div class="tc-info">
                <div class="tc-label">Pemasukan</div>
                <div class="tc-value">Rp {{ formatShort(todayIncomeAmount) }}</div>
                <div class="tc-count">{{ todayIncomeCount }} transaksi</div>
              </div>
            </div>
            <div class="tc-item">
              <div class="tc-icon down-bg">📉</div>
              <div class="tc-info">
                <div class="tc-label">Pengeluaran</div>
                <div class="tc-value">Rp {{ formatShort(todayExpense) }}</div>
                <div class="tc-count">{{ todayExpenseCount }} transaksi</div>
              </div>
            </div>
            <div class="tc-item">
              <div class="tc-icon budget-bg">🥧</div>
              <div class="tc-info">
                <div class="tc-label">Budget Terpakai</div>
                <div class="tc-value">{{ budgetPct ?? 0 }}%</div>
                <div class="tc-count">Rp {{ formatShort(totalExpense) }}</div>
              </div>
            </div>
          </div>
        </div>
      </div>

      <!-- Goals preview -->
      <div v-if="goals && goals.length > 0" class="section">
        <div class="section-head">
          <span class="section-title">🎯 Goals Tabungan</span>
          <Link :href="route('saving.index')" class="see-all">Lihat semua →</Link>
        </div>
        <div class="goal-scroll">
          <div v-for="g in goals" :key="g.id" class="goal-chip">
            <div class="goal-name">{{ g.name }}</div>
            <div class="goal-bar">
              <div class="goal-fill" :style="`width:${g.percent}%`"></div>
            </div>
            <div class="goal-pct">{{ g.percent }}%</div>
          </div>
        </div>
      </div>

      <!-- Upcoming bills -->
      <div v-if="upcomingBills.length > 0" class="section">
        <div class="section-head">
          <span class="section-title">📋 Tagihan Minggu Ini</span>
        </div>
        <div class="bill-list">
          <div v-for="bill in upcomingBills" :key="bill.name"
            :class="['bill-chip', bill.days_until_due === 0 ? 'today' : bill.days_until_due <= 3 ? 'soon' : '']">
            <span class="bc-emoji">{{ bill.emoji || '📋' }}</span>
            <span class="bc-name">{{ bill.name }}</span>
            <span class="bc-due">{{ bill.days_until_due === 0 ? 'Hari ini!' : `H-${bill.days_until_due}` }}</span>
            <span class="bc-amt">{{ formatShort(bill.amount) }}</span>
          </div>
        </div>
      </div>

      <!-- Transaksi Terbaru -->
      <div class="section">
        <div class="section-head">
          <span class="section-title">Transaksi Terbaru</span>
        </div>
        <div class="card tx-card">
          <div v-if="recentTransactions.length === 0" class="empty-state">
            <div class="empty-illust">📝</div>
            <div class="empty-text">Belum ada transaksi</div>
            <div class="empty-sub">Mulai catat transaksi pertamamu!</div>
            <Link :href="route('dompet.index')" class="btn-primary" style="margin-top:14px;max-width:200px;">
              + Catat Sekarang
            </Link>
          </div>

          <div v-for="tx in recentTransactions" :key="tx.id" class="tx-item">
            <div class="tx-icon" :style="`background:${tx.type === 'income' ? 'var(--success-bg)' : 'var(--danger-bg)'}`">
              <img v-if="tx.category_icon_url" :src="tx.category_icon_url" class="tx-icon-img" alt="" />
              <span v-else>{{ tx.category_emoji || (tx.type === 'income' ? '💵' : '🛍️') }}</span>
            </div>
            <div class="tx-info">
              <div class="tx-name">{{ tx.note || tx.category || 'Transaksi' }}</div>
              <div class="tx-cat">{{ tx.category }} · {{ tx.wallet }}</div>
            </div>
            <div class="tx-right">
              <div :class="['tx-amt', tx.type === 'income' ? 'up' : 'down']">
                {{ tx.type === 'income' ? '+' : '−' }}{{ formatShort(tx.amount) }}
              </div>
              <div class="tx-time">{{ tx.transacted_at }}</div>
            </div>
          </div>

          <div v-if="recentTransactions.length > 0" class="see-all-row">
            <Link :href="route('dompet.index')" class="see-all">Lihat semua →</Link>
          </div>
        </div>
      </div>

      <!-- Kategori Pengeluaran Bulan Ini -->
      <div class="section" v-if="topCategories.length > 0">
        <div class="section-head">
          <span class="section-title">Kategori Pengeluaran</span>
        </div>
        <div class="card">
          <div v-for="cat in topCategories" :key="cat.name" class="cat-row">
            <div class="cat-emoji">{{ cat.emoji }}</div>
            <div class="cat-info">
              <div class="cat-name-row">
                <span class="cat-name">{{ cat.name }}</span>
                <span class="cat-amt">Rp {{ formatShort(cat.total) }}</span>
              </div>
              <div class="cat-bar-bg">
                <div class="cat-bar-fill" :style="`width:${cat.percent}%`"></div>
              </div>
            </div>
            <div class="cat-pct">{{ cat.percent }}%</div>
          </div>
          <div class="see-all-row">
            <Link :href="route('report')" class="see-all">Lihat laporan lengkap →</Link>
          </div>
        </div>
      </div>

    </div>

    <!-- Notification Panel -->
    <Teleport to="body">
      <div v-if="showNotif" class="notif-overlay" @click="showNotif = false"></div>
      <div :class="['notif-panel', { active: showNotif }]">
        <div class="notif-header">
          <h2>Notifikasi 🔔</h2>
          <button class="notif-close" @click="showNotif = false">✕</button>
        </div>
        <div v-if="notifications.length === 0" class="empty-state" style="padding:24px;">
          <div class="empty-illust">✅</div>
          <div class="empty-text">Tidak ada notifikasi baru</div>
        </div>
        <div v-for="notif in notifications" :key="notif.id" class="notif-item">
          <div class="notif-icon">🔔</div>
          <div class="notif-body">
            <div class="notif-title">{{ notif.title }}</div>
            <div class="notif-desc">{{ notif.body }}</div>
            <div class="notif-time">{{ notif.created_at }}</div>
          </div>
        </div>
      </div>
    </Teleport>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import AppIcon from '@/Components/AppIcon.vue'

const props = defineProps({
  totalBalance: Number, totalIncome: Number, totalExpense: Number,
  todayExpense: Number, todayExpenseCount: Number,
  todayIncomeAmount: Number, todayIncomeCount: Number,
  prevMonthLabel: String, incomeTrendUp: Boolean, expenseTrendUp: Boolean,
  budgetHarian: Number, budgetPct: Number, totalBudget: Number,
  recentTransactions: Array, notifications: Array, upcomingBills: Array,
  goals: { type: Array, default: () => [] },
  topCategories: { type: Array, default: () => [] },
  period: String, hide_balance: Boolean,
})

const page = usePage()
const showNotif = ref(false)
const balanceHidden = ref(props.hide_balance)

const firstName = computed(() => page.props.auth.user?.name?.split(' ')[0] ?? '')
const initials = computed(() => {
  const name = page.props.auth.user?.name ?? ''
  return name.split(' ').slice(0, 2).map(w => w[0]).join('').toUpperCase()
})

const greetingTime = computed(() => {
  const h = new Date().getHours()
  if (h < 11) return 'Pagi'
  if (h < 15) return 'Siang'
  if (h < 18) return 'Sore'
  return 'Malam'
})

const toggleBalance = async () => {
  balanceHidden.value = !balanceHidden.value
  try {
    await fetch(route('dashboard.toggle-balance'), {
      method: 'POST',
      headers: {
        'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]')?.content ?? '',
        'Content-Type': 'application/json',
      },
    })
  } catch {}
}

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000_000) return (n/1_000_000_000).toFixed(1) + 'M'
  if (n >= 1_000_000)     return (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)         return (n/1_000).toFixed(0) + 'rb'
  return String(n)
}
</script>

<style scoped>
.dash-hero-bg {
  background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dark) 100%);
  padding: 20px 20px 0;
  position: relative;
  overflow: hidden;
}
.dash-hero-bg::before {
  content: '';
  position: absolute;
  top: -60px; right: -40px;
  width: 200px; height: 200px;
  border-radius: 50%;
  background: rgba(255,255,255,.06);
}
.dash-hero-bg::after {
  content: '';
  position: absolute;
  top: 60px; right: 90px;
  width: 90px; height: 90px;
  border-radius: 50%;
  background: rgba(255,255,255,.05);
}

.dash-header { display:flex; justify-content:space-between; align-items:flex-start; position:relative; z-index:2; margin-bottom:16px; }
.greeting-label { font-size:13px; color:rgba(255,255,255,.85); font-weight:500; }
.greeting-name { font-family:'Plus Jakarta Sans',sans-serif; font-size:24px; font-weight:800; color:white; letter-spacing:-.02em; margin-top:2px; }
.greeting-sub { font-size:12px; color:rgba(255,255,255,.7); margin-top:4px; }
.header-actions { display:flex; gap:10px; align-items:center; flex-shrink:0; }
.icon-btn-round { position:relative; width:38px; height:38px; border-radius:50%; background:rgba(255,255,255,.18); border:none; cursor:pointer; display:flex; align-items:center; justify-content:center; font-size:16px; }
.notif-badge { position:absolute; top:-3px; right:-3px; background:var(--danger); color:white; font-size:9px; font-weight:800; width:16px; height:16px; border-radius:50%; display:flex; align-items:center; justify-content:center; border:2px solid var(--primary); }
.avatar-btn { width:38px; height:38px; border-radius:50%; background:white; color:var(--primary-dark); display:flex; align-items:center; justify-content:center; font-size:13px; font-weight:800; text-decoration:none; }

.hero-card {
  position: relative; z-index: 2; overflow: hidden;
  background: white; border-radius: 22px 22px 0 0;
  padding: 22px 20px 20px;
  box-shadow: 0 -4px 20px rgba(0,0,0,.04);
}
.hero-illustration { position:absolute; right:16px; top:18px; width:64px; height:64px; opacity:.95; pointer-events:none; }
.hero-top { display:flex; justify-content:space-between; align-items:center; }
.hero-label { font-size:11px; font-weight:700; letter-spacing:.06em; color:var(--text-secondary); }
.hide-btn { background:var(--background); border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:var(--text-secondary); }
.hide-btn svg { width:16px; height:16px; }
.hero-amount { font-family:'Plus Jakarta Sans',sans-serif; font-size:30px; font-weight:800; letter-spacing:-.02em; margin:6px 0 8px; color:var(--text-primary); }
.hidden-text { letter-spacing:.1em; color:var(--text-faint); font-size:24px; }

.hero-compare { display:flex; align-items:center; gap:8px; flex-wrap:wrap; font-size:13px; font-weight:700; padding-bottom:14px; border-bottom:1px solid var(--border); margin-bottom:14px; }
.hero-compare .trend.up { color: var(--success); }
.hero-compare .trend.down { color: var(--danger); }
.hero-compare .sep { color: var(--border); font-weight:400; }
.hero-compare .vs-label { font-size:11px; color:var(--text-secondary); font-weight:500; margin-left:auto; }

.hero-progress { }
.progress-label-row { display:flex; justify-content:space-between; font-size:10px; font-weight:700; letter-spacing:.05em; color:var(--text-secondary); margin-bottom:6px; }
.progress-pct { color:var(--primary); }
.progress-bar-bg { height:8px; background:var(--background); border-radius:99px; overflow:hidden; }
.progress-bar-fill { height:100%; background:linear-gradient(90deg, var(--primary) 0%, var(--primary-dark) 100%); border-radius:99px; transition:width .5s; }
.progress-sub { font-size:11px; color:var(--text-secondary); margin-top:6px; }

.page-content { padding: 16px 20px 0; }

/* Quick actions */
.qa-row { display:flex; gap:8px; margin-bottom:18px; }
.qa-btn { flex:1; display:flex; flex-direction:column; align-items:center; gap:6px; background:var(--surface); border-radius:var(--radius-lg); padding:14px 6px; box-shadow:var(--shadow-card); text-decoration:none; color:var(--text-primary); font-size:10.5px; font-weight:600; transition:all .15s; }
.qa-btn:active { transform:scale(.95); }
.qa-icon { width:38px; height:38px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; }
.qa-icon.pemasukan   { background:var(--success-bg); }
.qa-icon.pengeluaran { background:var(--danger-bg); }
.qa-icon.scan        { background:var(--primary-bg); }
.qa-icon.aset         { background:var(--amber-bg); }

/* Today summary */
.today-card { padding:16px 18px; }
.tc-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:14px; }
.tc-grid { display:grid; grid-template-columns:repeat(auto-fit,minmax(110px,1fr)); gap:14px; }
.tc-item { display:flex; align-items:center; gap:10px; }
.tc-icon { width:36px; height:36px; border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:16px; flex-shrink:0; }
.tc-icon.up-bg { background:var(--success-bg); }
.tc-icon.down-bg { background:var(--danger-bg); }
.tc-icon.budget-bg { background:var(--amber-bg); }
.tc-label { font-size:10px; color:var(--text-secondary); font-weight:600; }
.tc-value { font-size:13px; font-weight:800; }
.tc-count { font-size:10px; color:var(--text-faint); }

/* Section */
.section { margin-bottom:18px; }
.section-head { display:flex; justify-content:space-between; align-items:center; margin-bottom:10px; }
.section-title { font-size:13px; font-weight:700; }
.see-all { font-size:12px; color:var(--primary); text-decoration:none; font-weight:600; }
.see-all-row { text-align:center; padding-top:10px; border-top:1px solid var(--border); margin-top:4px; }

/* Goals */
.goal-scroll { display:flex; gap:10px; overflow-x:auto; scrollbar-width:none; }
.goal-chip { flex-shrink:0; min-width:140px; background:var(--surface); border-radius:var(--radius-lg); padding:14px; box-shadow:var(--shadow-card); }
.goal-name { font-size:12px; font-weight:600; margin-bottom:8px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.goal-bar { height:6px; background:var(--background); border-radius:99px; overflow:hidden; margin-bottom:4px; }
.goal-fill { height:100%; background:var(--secondary); border-radius:99px; }
.goal-pct { font-size:11px; color:var(--text-secondary); font-weight:600; }

/* Bills */
.bill-list { display:flex; flex-direction:column; gap:8px; }
.bill-chip { display:flex; align-items:center; gap:10px; background:var(--surface); border-radius:var(--radius-md); padding:12px 14px; box-shadow:var(--shadow-card); border-left:3px solid var(--success); }
.bill-chip.today { border-left-color:var(--danger); }
.bill-chip.soon  { border-left-color:var(--amber); }
.bc-emoji { font-size:18px; }
.bc-name { flex:1; font-size:13px; font-weight:500; }
.bc-due { font-size:11px; font-weight:700; color:var(--success); }
.bill-chip.today .bc-due { color:var(--danger); }
.bill-chip.soon .bc-due { color:var(--amber); }
.bc-amt { font-family:'Plus Jakarta Sans',sans-serif; font-size:12px; font-weight:800; }

/* Transactions */
.tx-card { padding:8px 16px; }
.tx-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); }
.tx-item:last-of-type { border-bottom:none; }
.tx-icon { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; overflow:hidden; }
.tx-icon-img { width:100%; height:100%; object-fit:contain; }
.tx-info { flex:1; min-width:0; }
.tx-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tx-cat { font-size:11px; color:var(--text-secondary); }
.tx-right { text-align:right; flex-shrink:0; }
.tx-amt { font-size:13px; font-weight:700; }
.tx-amt.up { color:var(--success); }
.tx-amt.down { color:var(--danger); }
.tx-time { font-size:10px; color:var(--text-faint); }

.empty-state { text-align:center; padding:24px 0; }
.empty-illust { font-size:36px; margin-bottom:8px; }
.empty-text { font-size:14px; font-weight:600; margin-bottom:2px; }
.empty-sub { font-size:12px; color:var(--text-secondary); }

/* Kategori Pengeluaran */
.cat-row { display: flex; align-items: center; gap: 10px; padding: 10px 0; border-bottom: 1px solid var(--border); }
.cat-row:last-of-type { border-bottom: none; }
.cat-emoji { font-size: 20px; width: 32px; text-align: center; flex-shrink: 0; }
.cat-info { flex: 1; min-width: 0; }
.cat-name-row { display: flex; justify-content: space-between; font-size: 13px; margin-bottom: 4px; }
.cat-name { font-weight: 600; color: var(--text-primary); }
.cat-amt { color: var(--text-secondary); }
.cat-bar-bg { height: 6px; background: var(--background); border-radius: 99px; overflow: hidden; }
.cat-bar-fill { height: 100%; background: var(--primary); border-radius: 99px; }
.cat-pct { font-size: 12px; font-weight: 700; color: var(--text-secondary); width: 36px; text-align: right; flex-shrink: 0; }

/* Notif panel */
.notif-overlay { position:fixed; inset:0; background:rgba(15,23,42,.4); z-index:400; backdrop-filter:blur(3px); }
.notif-panel { position:fixed; top:0; right:0; width:100%; max-width:480px; height:100vh; background:var(--surface); z-index:401; transform:translateX(100%); transition:transform .3s ease; overflow-y:auto; }
.notif-panel.active { transform:translateX(0); }
.notif-header { padding:20px; border-bottom:1px solid var(--border); display:flex; align-items:center; justify-content:space-between; position:sticky; top:0; background:var(--surface); }
.notif-header h2 { font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; font-weight:800; }
.notif-close { font-size:18px; background:var(--background); border:none; border-radius:50%; width:32px; height:32px; cursor:pointer; }
.notif-item { display:flex; gap:12px; padding:14px 20px; border-bottom:1px solid var(--border); background:var(--primary-bg); }
.notif-icon { font-size:20px; flex-shrink:0; }
.notif-title { font-size:13px; font-weight:600; margin-bottom:2px; }
.notif-desc { font-size:12px; color:var(--text-secondary); line-height:1.5; }
.notif-time { font-size:10px; color:var(--text-faint); margin-top:4px; }
</style>
