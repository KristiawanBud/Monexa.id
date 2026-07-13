<template>
  <div class="app-shell">

    <Transition name="flash">
      <div v-if="flash.success" class="flash-toast">✅ {{ flash.success }}</div>
    </Transition>
    <Transition name="flash">
      <div v-if="flash.error" class="flash-toast error">❌ {{ flash.error }}</div>
    </Transition>

    <!-- Desktop Sidebar Nav (≥1025px) -->
    <aside class="desktop-sidebar" aria-label="Navigasi utama">
      <Link :href="route('dashboard')" :class="['sb-item', { active: isActive('App/Dashboard') }]">
        <AppIcon slug="nav_dashboard" class="sb-icon">🏠</AppIcon>
        <span>Dashboard</span>
      </Link>
      <Link :href="route('dompet.index')" :class="['sb-item', { active: isActive('App/Dompet') }]">
        <AppIcon slug="nav_dompet" class="sb-icon">👛</AppIcon>
        <span>Dompet</span>
      </Link>
      <Link :href="route('report')" :class="['sb-item', { active: isActive('App/Report') }]">
        <AppIcon slug="nav_report" class="sb-icon">📊</AppIcon>
        <span>Laporan</span>
      </Link>
      <Link :href="route('account')" :class="['sb-item', { active: isActive('App/Account') }]">
        <AppIcon slug="nav_account" class="sb-icon">👤</AppIcon>
        <span>Profil</span>
        <span v-if="unreadCount > 0" class="sb-badge">{{ unreadCount }}</span>
      </Link>

      <button class="sb-add-btn" @click="openQuickAdd">
        <span class="sb-add-plus">＋</span> Tambah Transaksi
      </button>
    </aside>

    <main class="main-content">
      <slot />
    </main>

    <!-- Bottom Navigation (mobile & tablet, ≤1024px) -->
    <nav class="bottom-nav">
      <Link :href="route('dashboard')" :class="['bn-item', { active: isActive('App/Dashboard') }]">
        <AppIcon slug="nav_dashboard" class="bn-icon">🏠</AppIcon>
        <span class="bn-label">Dashboard</span>
      </Link>

      <Link :href="route('dompet.index')" :class="['bn-item', { active: isActive('App/Dompet') }]">
        <AppIcon slug="nav_dompet" class="bn-icon">👛</AppIcon>
        <span class="bn-label">Dompet</span>
      </Link>

      <div class="bn-center">
        <button class="fab" @click="openQuickAdd" aria-label="Tambah transaksi">
          <span class="fab-plus">＋</span>
        </button>
      </div>

      <Link :href="route('report')" :class="['bn-item', { active: isActive('App/Report') }]">
        <AppIcon slug="nav_report" class="bn-icon">📊</AppIcon>
        <span class="bn-label">Laporan</span>
      </Link>

      <Link :href="route('account')" :class="['bn-item', { active: isActive('App/Account') }]">
        <AppIcon slug="nav_account" class="bn-icon">👤</AppIcon>
        <span class="bn-label">Profil</span>
        <span v-if="unreadCount > 0" class="bn-badge">{{ unreadCount }}</span>
      </Link>
    </nav>

    <!-- Quick Add Bottom Sheet -->
    <Teleport to="body">
      <Transition name="overlay">
        <div v-if="showQuickAdd" class="qa-overlay" @click.self="showQuickAdd = false"></div>
      </Transition>

      <Transition name="sheet">
        <div v-if="showQuickAdd" class="qa-sheet">
          <div class="qa-handle"></div>
          <div class="qa-title">Tambah Transaksi</div>

          <div class="qa-grid">
            <button class="qa-card pemasukan" @click="goTo('income')">
              <AppIcon slug="qa_pemasukan" class="qac-icon">💵</AppIcon>
              <span class="qac-label">Pemasukan</span>
            </button>
            <button class="qa-card pengeluaran" @click="goTo('expense')">
              <AppIcon slug="qa_pengeluaran" class="qac-icon">🔥</AppIcon>
              <span class="qac-label">Pengeluaran</span>
            </button>
            <button class="qa-card scan" @click="goTo('scan')">
              <AppIcon slug="qa_scan" class="qac-icon">📷</AppIcon>
              <span class="qac-label">Scan Struk</span>
            </button>
            <button class="qa-card setor" @click="goTo('saving')">
              <AppIcon slug="qa_saving" class="qac-icon">🎯</AppIcon>
              <span class="qac-label">Setor Tabungan</span>
            </button>
            <button class="qa-card bayar" @click="goTo('bill')">
              <AppIcon slug="qa_bill" class="qac-icon">📋</AppIcon>
              <span class="qac-label">Bayar Tagihan</span>
            </button>
            <button class="qa-card import" @click="goTo('import')">
              <AppIcon slug="qa_import" class="qac-icon">📥</AppIcon>
              <span class="qac-label">Import Excel</span>
            </button>
            <button class="qa-card budget" @click="goTo('budget')">
              <AppIcon slug="qa_budget" class="qac-icon">💡</AppIcon>
              <span class="qac-label">Budget</span>
            </button>
            <button class="qa-card aset" @click="goTo('asset')">
              <AppIcon slug="qa_aset" class="qac-icon">💎</AppIcon>
              <span class="qac-label">Aset</span>
            </button>
          </div>

          <div class="qa-wa-hint">
            💬 Atau kirim ke WA Bot: <strong>"Makan 35rb"</strong>
          </div>

          <button class="qa-cancel" @click="showQuickAdd = false">Tutup</button>
        </div>
      </Transition>
    </Teleport>

    <!-- CuanAI -->
    <CuanAI />

  </div>
</template>

<script setup>
import { ref, computed } from 'vue'
import { Link, usePage, router } from '@inertiajs/vue3'
import CuanAI from '@/Components/CuanAI.vue'
import AppIcon from '@/Components/AppIcon.vue'
import { trackEvent } from '@/lib/analytics'

const page = usePage()
const showQuickAdd = ref(false)

const flash = computed(() => page.props.flash ?? {})
const unreadCount = computed(() => page.props.unread_notifications ?? 0)
const isActive = (component) => page.component === component

const openQuickAdd = () => {
  showQuickAdd.value = true
  trackEvent('quick_add_clicked', { action: 'open', surface: 'global-fab' })
}

const QUICK_ADD_ACTION_MAP = {
  income: 'add-income',
  expense: 'add-expense',
  scan: 'scan',
  saving: 'saving',
  bill: 'bill',
}

const goTo = (action) => {
  showQuickAdd.value = false
  trackEvent('quick_add_clicked', { action: QUICK_ADD_ACTION_MAP[action] ?? action, surface: 'global-fab' })
  switch (action) {
    case 'income':   router.visit(route('dompet.index'), { data: { tab: 'in' } }); break
    case 'expense':  router.visit(route('dompet.index'), { data: { tab: 'out' } }); break
    case 'scan':     router.visit(route('receipt.index')); break
    case 'saving':   router.visit(route('saving.index')); break
    case 'bill':     router.visit(route('dompet.index'), { data: { tab: 'bill' } }); break
    case 'import':   router.visit(route('import.index')); break
    case 'budget':   router.visit(route('budget.index')); break
    case 'asset':    router.visit(route('asset.index')); break
  }
}
</script>

<style scoped>
/* ── Breakpoints kontrak QA: mobile ≤480px, tablet 481-1024px, desktop ≥1025px ── */
.app-shell {
  max-width: 480px;
  margin: 0 auto;
  min-height: 100vh;
  background: var(--background);
  position: relative;
}

.main-content { padding-bottom: 88px; }

.desktop-sidebar { display: none; }

/* ── Tablet (481-1024px): container lebih lebar, bottom-nav tetap ── */
@media (min-width: 481px) {
  .app-shell {
    max-width: 100%;
    padding: 0 24px;
  }
  .bottom-nav {
    max-width: 600px;
    border-radius: var(--radius-lg) var(--radius-lg) 0 0;
  }
}

/* ── Desktop (≥1025px): sidebar kiri persisten, bottom-nav & FAB hilang ── */
@media (min-width: 1025px) {
  .app-shell {
    display: flex;
    align-items: flex-start;
    gap: 32px;
    max-width: 1280px;
    padding: 24px 32px;
  }

  .main-content { flex: 1; min-width: 0; padding-bottom: 0; }

  .bottom-nav { display: none; }

  .desktop-sidebar {
    display: flex;
    flex-direction: column;
    gap: 4px;
    width: 240px;
    flex-shrink: 0;
    position: sticky;
    top: 24px;
    background: var(--surface);
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 16px 12px;
  }
}

/* ── Desktop Sidebar Items ── */
.sb-item {
  display: flex;
  align-items: center;
  gap: 12px;
  padding: 12px 14px;
  border-radius: var(--radius-md);
  text-decoration: none;
  color: var(--text-secondary);
  font-size: 14px;
  font-weight: 600;
  font-family: 'Plus Jakarta Sans', sans-serif;
  position: relative;
  min-height: 44px;
  transition: background .15s, color .15s;
}
.sb-item:hover { background: var(--background); }
.sb-item.active { background: var(--primary-bg); color: var(--primary); }
.sb-icon { font-size: 20px; line-height: 1; filter: grayscale(1) opacity(.55); }
.sb-item.active .sb-icon { filter: grayscale(0) opacity(1); }
.sb-badge {
  margin-left: auto;
  background: var(--danger);
  color: white;
  font-size: 10px;
  font-weight: 700;
  padding: 1px 6px;
  border-radius: 99px;
  min-width: 16px;
  text-align: center;
}
.sb-add-btn {
  display: flex;
  align-items: center;
  justify-content: center;
  gap: 8px;
  width: 100%;
  margin-top: 12px;
  padding: 13px;
  min-height: 44px;
  background: var(--primary);
  color: white;
  border: none;
  border-radius: var(--radius-md);
  font-size: 14px;
  font-weight: 700;
  font-family: 'Plus Jakarta Sans', sans-serif;
  cursor: pointer;
  box-shadow: var(--shadow-sm);
  transition: background .2s;
}
.sb-add-btn:hover { background: var(--primary-dark); }
.sb-add-plus { font-size: 16px; }

/* ── Bottom Nav ── */
.bottom-nav {
  position: fixed;
  bottom: 0;
  left: 50%;
  transform: translateX(-50%);
  width: 100%;
  max-width: 480px;
  display: flex;
  align-items: center;
  background: var(--surface);
  border-top: 1px solid var(--border);
  height: 72px;
  padding: 0 4px 12px;
  z-index: 100;
  box-shadow: 0 -4px 20px rgba(15,23,42,.05);
}

.bn-item {
  flex: 1;
  display: flex;
  flex-direction: column;
  align-items: center;
  justify-content: center;
  gap: 4px;
  text-decoration: none;
  color: var(--text-faint);
  font-size: 10px;
  font-weight: 600;
  padding: 8px 4px;
  transition: all .15s;
  position: relative;
  font-family: 'Plus Jakarta Sans', sans-serif;
}

.bn-item.active { color: var(--primary); }
.bn-icon { font-size: 22px; line-height: 1; filter: grayscale(1) opacity(.55); transition: filter .15s, transform .15s; }
.bn-item.active .bn-icon { filter: grayscale(0) opacity(1); transform: translateY(-1px); }
.bn-label { font-size: 10px; }

.bn-badge {
  position: absolute;
  top: 2px; right: 8px;
  background: var(--danger);
  color: white;
  font-size: 9px;
  font-weight: 700;
  padding: 1px 5px;
  border-radius: 99px;
  min-width: 16px;
  text-align: center;
}

/* ── FAB Center ── */
.bn-center { flex: 1; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
.fab-plus { color: white; font-size: 26px; font-weight: 300; line-height: 1; margin-top: -1px; }

/* ── Flash Toast ── */
.flash-toast {
  position: fixed; top: 16px; left: 50%; transform: translateX(-50%);
  z-index: 9999; pointer-events: none;
}
.flash-enter-active, .flash-leave-active { transition: all .3s ease; }
.flash-enter-from, .flash-leave-to { opacity: 0; transform: translateX(-50%) translateY(-12px); }

/* ── Quick Add Sheet ── */
.qa-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 400; backdrop-filter: blur(4px); }
.qa-sheet {
  position: fixed; bottom: 0; left: 50%; transform: translateX(-50%);
  width: 100%; max-width: 480px;
  background: var(--surface);
  border-radius: 28px 28px 0 0;
  padding: 16px 20px 40px;
  z-index: 401;
  box-shadow: 0 -10px 40px rgba(15,23,42,.15);
}
.qa-handle { width: 40px; height: 4px; background: var(--border); border-radius: 99px; margin: 0 auto 20px; }
.qa-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 18px; font-weight: 800; margin-bottom: 18px; text-align: center; }

.qa-grid { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 10px; margin-bottom: 18px; }
.qa-card {
  display: flex; flex-direction: column; align-items: center; gap: 8px;
  padding: 16px 8px; border-radius: var(--radius-lg); border: none; cursor: pointer;
  transition: all .2s; background: var(--background);
}
.qa-card:active { transform: scale(.95); }
.qa-card.pemasukan   { background: var(--success-bg); }
.qa-card.pengeluaran { background: var(--danger-bg); }
.qa-card.scan        { background: var(--primary-bg); }
.qa-card.setor       { background: var(--amber-bg); }
.qa-card.bayar       { background: var(--purple-bg); }
.qa-card.import      { background: var(--secondary-bg); }
.qa-card.budget      { background: var(--amber-bg); }
.qa-card.aset        { background: var(--primary-bg); }

.qac-icon { font-size: 26px; }
.qac-label { font-size: 11px; font-weight: 600; color: var(--text-primary); line-height: 1.3; text-align: center; }

.qa-wa-hint {
  text-align: center; font-size: 12px; color: var(--text-secondary);
  background: var(--background); border-radius: var(--radius-md);
  padding: 10px 14px; margin-bottom: 14px; line-height: 1.6;
}

.qa-cancel {
  width: 100%; padding: 13px; background: none; border: 1.5px solid var(--border);
  border-radius: var(--radius-md); font-size: 14px; font-weight: 600; color: var(--text-secondary);
  cursor: pointer; font-family: inherit; transition: all .15s;
}
.qa-cancel:hover { border-color: var(--primary); color: var(--primary); }

.sheet-enter-active, .sheet-leave-active { transition: transform .3s cubic-bezier(.4,0,.2,1); }
.sheet-enter-from, .sheet-leave-to { transform: translateX(-50%) translateY(100%); }
.overlay-enter-active, .overlay-leave-active { transition: opacity .3s ease; }
.overlay-enter-from, .overlay-leave-to { opacity: 0; }
</style>
