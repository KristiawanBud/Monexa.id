<template>
  <div class="app-shell">

    <Transition name="flash">
      <div v-if="flash.success" class="flash-toast">✅ {{ flash.success }}</div>
    </Transition>
    <Transition name="flash">
      <div v-if="flash.error" class="flash-toast error">❌ {{ flash.error }}</div>
    </Transition>

    <main class="main-content">
      <slot />
    </main>

    <!-- Bottom Navigation -->
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
        <button class="fab" @click="showQuickAdd = true" aria-label="Tambah Transaksi">
          <span class="fab-plus">＋</span>
          <span class="fab-label">Tambah Transaksi</span>
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

const page = usePage()
const showQuickAdd = ref(false)

const flash = computed(() => page.props.flash ?? {})
const unreadCount = computed(() => page.props.unread_notifications ?? 0)
const isActive = (component) => page.component === component

const goTo = (action) => {
  showQuickAdd.value = false
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
/* ── Breakpoints ──
   xs  < 480px   (mobile kecil)
   sm  480–767px (mobile besar)
   md  768–1023px (tablet)
   lg  1024–1279px (desktop kecil)
   xl  >= 1280px  (desktop besar)
*/

.app-shell {
  max-width: 480px;
  margin: 0 auto;
  min-height: 100vh;
  background: var(--background);
  position: relative;
}

@media (min-width: 768px) {
  .app-shell { max-width: 100%; }
}

@media (min-width: 1024px) {
  .app-shell { display: flex; }
}

.main-content { padding-bottom: 88px; }

@media (min-width: 1024px) {
  .main-content {
    flex: 1;
    margin-left: 240px;
    padding-bottom: 32px;
  }
}

/* ── Bottom Nav (mobile & tablet) / Sidebar Nav (desktop) ── */
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

@media (min-width: 768px) and (max-width: 1023px) {
  .bottom-nav { max-width: 600px; }
}

@media (min-width: 1024px) {
  .bottom-nav {
    left: 0;
    top: 0;
    bottom: 0;
    transform: none;
    width: 240px;
    max-width: 240px;
    height: 100vh;
    flex-direction: column;
    align-items: stretch;
    justify-content: flex-start;
    padding: 24px 12px;
    gap: 4px;
    border-top: none;
    border-right: 1px solid var(--border);
    box-shadow: none;
  }
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
  min-height: 44px;
}

@media (min-width: 1024px) {
  .bn-item {
    flex: none;
    flex-direction: row;
    justify-content: flex-start;
    gap: 12px;
    padding: 12px 14px;
    border-radius: var(--radius-md);
    font-size: 13px;
  }
  .bn-item.active { background: var(--primary-bg); }
}

.bn-item.active { color: var(--primary); }
.bn-icon { font-size: 22px; line-height: 1; filter: grayscale(1) opacity(.55); transition: filter .15s, transform .15s; }
.bn-item.active .bn-icon { filter: grayscale(0) opacity(1); transform: translateY(-1px); }
@media (min-width: 1024px) {
  .bn-item.active .bn-icon { transform: none; }
}
.bn-label { font-size: 10px; }
@media (min-width: 1024px) {
  .bn-label { font-size: 13px; }
}

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

@media (min-width: 1024px) {
  .bn-badge { top: 8px; right: 10px; }
}

/* ── FAB Center (mobile/tablet) / Tombol Tambah (desktop sidebar) ── */
.bn-center { flex: 1; display: flex; align-items: center; justify-content: center; margin-bottom: 16px; }
.fab-plus { color: white; font-size: 26px; font-weight: 300; line-height: 1; margin-top: -1px; }
.fab-label { display: none; }

@media (min-width: 1024px) {
  .bn-center { margin-bottom: 8px; order: -1; }
  .fab {
    width: 100%;
    height: auto;
    border-radius: var(--radius-md);
    flex-direction: row;
    gap: 10px;
    padding: 13px 16px;
    font-size: 15px;
    font-weight: 700;
    font-family: 'Plus Jakarta Sans', sans-serif;
  }
  .fab-plus { font-size: 18px; margin-top: 0; }
  .fab-label { display: inline; }
}

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
