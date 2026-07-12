<template>
  <AppLayout>
    <div class="page-content">

      <!-- Hero -->
      <div class="dompet-hero-bg">
        <div class="hero-top-row">
          <div>
            <h1 class="hero-page-title">Dompet 👛</h1>
            <div class="hero-page-sub">Kelola semua rekening dan uangmu di sini.</div>
          </div>
          <button class="hero-add-btn" @click="openAddForTab">＋</button>
        </div>

        <AppIcon slug="dompet_hero" class="dompet-hero-illustration">👛</AppIcon>

        <div class="hero-saldo-row">
          <span class="hero-saldo-label">TOTAL SALDO</span>
          <button class="hero-eye-btn" @click="balanceHidden = !balanceHidden">
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
          <span v-if="!balanceHidden">{{ formatRupiah(total_balance) }}</span>
          <span v-else class="hidden-text">••••••••••</span>
        </div>
        <div class="hero-wallet-badge">● {{ active_wallets_count }} Dompet Aktif</div>
      </div>

      <!-- Breakdown Saldo -->
      <div class="card breakdown-card">
        <div class="breakdown-item">
          <div class="bd-icon cash">💵</div>
          <div class="bd-info">
            <div class="bd-label">Saldo Cash</div>
            <div class="bd-value cash">{{ formatShort(cash_total) }}</div>
            <div class="bd-bar-bg"><div class="bd-bar-fill cash" :style="`width:${total_balance ? Math.min(100, (cash_total/total_balance)*100) : 0}%`"></div></div>
          </div>
        </div>
        <div class="breakdown-item">
          <div class="bd-icon bank">🏦</div>
          <div class="bd-info">
            <div class="bd-label">Saldo Bank</div>
            <div class="bd-value bank">{{ formatShort(bank_total) }}</div>
            <div class="bd-bar-bg"><div class="bd-bar-fill bank" :style="`width:${total_balance ? Math.min(100, (bank_total/total_balance)*100) : 0}%`"></div></div>
          </div>
        </div>
        <div class="breakdown-item">
          <div class="bd-icon ewallet">👛</div>
          <div class="bd-info">
            <div class="bd-label">E-Wallet</div>
            <div class="bd-value ewallet">{{ formatShort(ewallet_total) }}</div>
            <div class="bd-bar-bg"><div class="bd-bar-fill ewallet" :style="`width:${total_balance ? Math.min(100, (ewallet_total/total_balance)*100) : 0}%`"></div></div>
          </div>
        </div>
      </div>

      <!-- Tabs -->
      <div class="tab-row">
        <button :class="['chip', { active: tab === 'transaksi' }]" @click="tab = 'transaksi'">Transaksi</button>
        <button :class="['chip', { active: tab === 'dompet' }]" @click="tab = 'dompet'">Dompet</button>
        <button :class="['chip', { active: tab === 'tagihan' }]" @click="tab = 'tagihan'">Tagihan</button>
      </div>

      <!-- ═══════════ TAB: TRANSAKSI ═══════════ -->
      <div v-if="tab === 'transaksi'">

        <!-- Filter periode + ringkasan -->
        <div class="range-filter-row">
          <div class="range-dropdown">
            <button class="range-btn" @click="showRangeMenu = !showRangeMenu">
              📅 {{ range_label }} <span class="range-caret">▾</span>
            </button>
            <div v-if="showRangeMenu" class="range-menu">
              <button @click="changeRange('today')">Hari Ini</button>
              <button @click="changeRange('week')">Minggu Ini</button>
              <button @click="changeRange('month')">Bulan Ini</button>
            </div>
          </div>
          <div class="range-stat">
            <span class="rs-label">↓ Masuk</span>
            <span class="rs-val up">{{ formatShort(total_income) }}</span>
          </div>
          <div class="range-stat">
            <span class="rs-label">↑ Keluar</span>
            <span class="rs-val down">{{ formatShort(total_expense) }}</span>
          </div>
          <div class="range-stat">
            <span class="rs-label">Saldo</span>
            <span class="rs-val">{{ formatShort(total_balance) }}</span>
          </div>
        </div>

        <!-- Search + Filter -->
        <div class="search-row">
          <div class="search-box">
            <span class="search-icon">🔍</span>
            <input v-model="searchQuery" @keyup.enter="applySearch" type="text" placeholder="Cari transaksi..." />
          </div>
          <button class="filter-btn" @click="alert('Filter lanjutan segera hadir')">▤ Filter</button>
        </div>

        <div class="tx-list-heading">
          <span class="section-title">Transaksi {{ range_label }}</span>
        </div>

        <!-- Transaction List -->
        <div class="card tx-list-card">
          <div v-if="!transactions.data || transactions.data.length === 0" class="empty-state">
            <div class="empty-illust">📝</div>
            <div class="empty-text">Belum ada transaksi bulan ini</div>
            <button class="btn-primary" style="margin-top:14px;max-width:220px;" @click="showAddTx = true">
              + Catat Transaksi
            </button>
          </div>

          <div v-for="t in transactions.data" :key="t.id" class="tx-item" @click="openEditTx(t)">
            <div class="tx-icon" :style="`background:${t.type === 'income' ? 'var(--success-bg)' : 'var(--danger-bg)'}`">
              {{ t.category_emoji || (t.type === 'income' ? '💵' : '🛍️') }}
            </div>
            <div class="tx-info">
              <div class="tx-name">{{ t.note || t.category || 'Transaksi' }}</div>
              <div class="tx-cat">{{ t.category }} · {{ t.wallet }} · {{ t.transacted_at_label }}</div>
            </div>
            <div :class="['tx-amt', t.type === 'income' ? 'up' : 'down']">
              {{ t.type === 'income' ? '+' : '−' }}{{ formatShort(t.amount) }}
            </div>
          </div>
        </div>
      </div>

      <!-- ═══════════ TAB: DOMPET ═══════════ -->
      <div v-if="tab === 'dompet'">

        <div class="summary-row single">
          <div class="summary-item full">
            <div class="summary-label">💰 Total Saldo Semua Dompet</div>
            <div class="summary-val">{{ formatRupiah(total_balance) }}</div>
          </div>
        </div>

        <button v-if="wallets.length >= 2" class="transfer-btn" @click="showTransfer = true">
          🔄 Transfer Antar Dompet
        </button>

        <div v-if="wallets.length === 0" class="empty-state card">
          <div class="empty-illust">👛</div>
          <div class="empty-text">Belum ada dompet</div>
          <button class="btn-primary" style="margin-top:14px;max-width:220px;" @click="showAddWallet = true">
            + Tambah Dompet
          </button>
        </div>

        <div v-for="w in wallets" :key="w.id" class="card wallet-card" @click="openEditWallet(w)">
          <div class="wallet-row">
            <div class="wallet-logo" :style="`background:${w.bank_color}`">
              <img v-if="w.logo_url" :src="w.logo_url" class="wallet-logo-img" />
              <span v-else>{{ w.bank_initial }}</span>
            </div>
            <div class="wallet-info">
              <div class="wallet-name">{{ w.display_name }}</div>
              <div class="wallet-type">{{ w.is_saham ? 'Saham' : typeLabel(w.type) }}</div>
            </div>
            <div class="wallet-balance">{{ formatRupiah(w.balance) }}</div>
          </div>
        </div>

        <button class="add-wallet-btn" @click="showAddWallet = true">
          <span style="font-size:20px;">＋</span> Tambah Dompet Baru
        </button>
      </div>

      <!-- ═══════════ TAB: TAGIHAN ═══════════ -->
      <div v-if="tab === 'tagihan'">

        <div v-if="bills.length === 0" class="empty-state card">
          <div class="empty-illust">📋</div>
          <div class="empty-text">Belum ada tagihan</div>
          <button class="btn-primary" style="margin-top:14px;max-width:220px;" @click="showAddBill = true">
            + Tambah Tagihan
          </button>
        </div>

        <div v-for="b in bills" :key="b.id" class="card bill-card">
          <div class="bill-row">
            <div class="bill-icon">{{ b.emoji || '📋' }}</div>
            <div class="bill-info">
              <div class="bill-name">{{ b.name }}</div>
              <div class="bill-due" :class="b.status_color">
                {{ b.is_paid_this_month ? '✅ Lunas bulan ini' : dueLabel(b.days_until_due) }}
              </div>
            </div>
            <div class="bill-amt">{{ formatShort(b.amount) }}</div>
          </div>
          <button v-if="!b.is_paid_this_month" class="bill-pay-btn" @click="openPayBill(b)">
            Bayar Sekarang
          </button>
        </div>

        <button class="add-wallet-btn" @click="showAddBill = true">
          <span style="font-size:20px;">＋</span> Tambah Tagihan Baru
        </button>
      </div>

    </div>

    <!-- ═══════════ MODAL: Tambah/Edit Transaksi ═══════════ -->
    <Teleport to="body">
      <div v-if="showAddTx" class="modal-overlay" @click.self="closeTxModal">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">{{ editingTx ? 'Edit Transaksi' : 'Tambah Transaksi' }}</div>

          <div class="type-toggle">
            <button :class="['type-btn', { active: txForm.type === 'income' }]" @click="txForm.type = 'income'">💵 Pemasukan</button>
            <button :class="['type-btn', { active: txForm.type === 'expense' }]" @click="txForm.type = 'expense'">🔥 Pengeluaran</button>
          </div>

          <form @submit.prevent="submitTx">
            <div class="form-group">
              <label class="form-label">Jumlah (Rp)</label>
              <input v-model="amountDisplay" @input="onAmountInput" type="text" inputmode="numeric"
                class="form-input-cc amount-input" placeholder="0" required />
            </div>
            <div class="form-group">
              <label class="form-label">Dompet</label>
              <select v-model="txForm.wallet_id" class="form-input-cc" required>
                <option value="">Pilih dompet...</option>
                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.display_name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Kategori</label>
              <select v-model="txForm.category_id" class="form-input-cc">
                <option value="">Pilih kategori...</option>
                <option v-for="c in filteredCategories" :key="c.id" :value="c.id">{{ c.emoji }} {{ c.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Catatan</label>
              <input v-model="txForm.note" type="text" class="form-input-cc" placeholder="Contoh: Makan siang" />
            </div>
            <div class="form-group">
              <label class="form-label">Tanggal</label>
              <input v-model="txForm.transacted_at" type="date" class="form-input-cc" required />
            </div>
            <button type="submit" class="btn-primary" :disabled="txForm.processing">
              {{ txForm.processing ? 'Menyimpan...' : 'Simpan Transaksi' }}
            </button>
            <button v-if="editingTx" type="button" class="btn-secondary" style="margin-top:10px;color:var(--danger);border-color:var(--danger);" @click="deleteTx">
              🗑️ Hapus Transaksi
            </button>
          </form>
        </div>
      </div>

      <!-- ═══════════ MODAL: Tambah/Edit Dompet ═══════════ -->
      <div v-if="showAddWallet" class="modal-overlay" @click.self="showAddWallet = false; editingWallet = null">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">{{ editingWallet ? 'Edit Dompet' : 'Tambah Dompet Baru' }}</div>
          <form @submit.prevent="submitWallet">
            <div class="form-group" v-if="!editingWallet">
              <label class="form-label">Pilih Bank</label>
              <select v-model="walletForm.bank_id" class="form-input-cc" @change="setBankName">
                <option value="">💵 Cash / Uang Tunai</option>
                <option v-for="bank in banks" :key="bank.id" :value="bank.id">{{ bank.short_name }} — {{ bank.name }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Nama Tampilan</label>
              <input v-model="walletForm.display_name" type="text" class="form-input-cc" placeholder="BCA Utama" required />
            </div>
            <div class="form-group" v-if="!editingWallet">
              <label class="form-label">Saldo Awal (Rp)</label>
              <input v-model="walletForm.initial_balance" type="number" class="form-input-cc" placeholder="0" />
            </div>
            <div class="form-group">
              <label class="form-label">Fungsi</label>
              <select v-model="walletForm.type" class="form-input-cc">
                <option value="both">Keluar-Masuk & Tabungan</option>
                <option value="cash_flow">Keluar-Masuk saja</option>
                <option value="saving">Tabungan saja</option>
              </select>
            </div>
            <button type="submit" class="btn-primary" :disabled="walletForm.processing">
              {{ walletForm.processing ? 'Menyimpan...' : 'Simpan Dompet' }}
            </button>
            <button v-if="editingWallet" type="button" class="btn-secondary danger-text" style="margin-top:10px;" @click="deleteWallet">
              🗑️ Hapus Dompet
            </button>
          </form>
        </div>
      </div>

      <!-- ═══════════ MODAL: Transfer Antar Dompet ═══════════ -->
      <div v-if="showTransfer" class="modal-overlay" @click.self="showTransfer = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">🔄 Transfer Antar Dompet</div>
          <form @submit.prevent="submitTransfer">
            <div class="form-group">
              <label class="form-label">Dari Dompet</label>
              <select v-model="transferForm.from_wallet_id" class="form-input-cc" required>
                <option value="">Pilih dompet asal...</option>
                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.display_name }} — {{ formatRupiah(w.balance) }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Ke Dompet</label>
              <select v-model="transferForm.to_wallet_id" class="form-input-cc" required>
                <option value="">Pilih dompet tujuan...</option>
                <option v-for="w in wallets" :key="w.id" :value="w.id" :disabled="w.id === transferForm.from_wallet_id">
                  {{ w.display_name }}
                </option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Jumlah (Rp)</label>
              <input v-model="transferAmountDisplay" @input="onTransferAmountInput" type="text" inputmode="numeric"
                class="form-input-cc amount-input" placeholder="0" required />
            </div>
            <div class="form-group">
              <label class="form-label">Catatan (opsional)</label>
              <input v-model="transferForm.note" type="text" class="form-input-cc" placeholder="Contoh: Pindah ke tabungan" />
            </div>
            <div class="form-group">
              <label class="form-label">Tanggal</label>
              <input v-model="transferForm.transferred_at" type="date" class="form-input-cc" required />
            </div>
            <button type="submit" class="btn-primary" :disabled="transferForm.processing">
              {{ transferForm.processing ? 'Memproses...' : '🔄 Transfer Sekarang' }}
            </button>
          </form>
        </div>
      </div>

      <!-- ═══════════ MODAL: Tambah Tagihan ═══════════ -->
      <div v-if="showAddBill" class="modal-overlay" @click.self="showAddBill = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">Tambah Tagihan</div>
          <form @submit.prevent="submitBill">
            <div class="form-group">
              <label class="form-label">Nama Tagihan</label>
              <input v-model="billForm.name" type="text" class="form-input-cc" placeholder="Listrik PLN" required />
            </div>

            <div class="form-group">
              <label class="form-label">Emoji</label>
              <EmojiPicker v-model="billForm.emoji" />
            </div>

            <div class="form-group">
              <label class="form-label">Jumlah (Rp)</label>
              <input v-model="billAmountDisplay" @input="onBillAmountInput" type="text" inputmode="numeric"
                class="form-input-cc amount-input" placeholder="0" required />
            </div>
            <div class="form-group">
              <label class="form-label">Tipe</label>
              <select v-model="billForm.type" class="form-input-cc">
                <option value="recurring">Berulang Bulanan</option>
                <option value="one_time">Sekali Bayar</option>
              </select>
            </div>
            <div class="form-group" v-if="billForm.type === 'recurring'">
              <label class="form-label">Tanggal Jatuh Tempo (1-31)</label>
              <input v-model="billForm.due_day" type="number" min="1" max="31" class="form-input-cc" />
            </div>
            <div class="form-group" v-else>
              <label class="form-label">Tanggal Jatuh Tempo</label>
              <input v-model="billForm.due_date" type="date" class="form-input-cc" />
            </div>
            <div class="form-group">
              <label class="form-label">Ingatkan (hari sebelum)</label>
              <div class="remind-pills">
                <button type="button" v-for="d in [7,3,1,0]" :key="d"
                  :class="['remind-pill', { selected: billForm.remind_days.includes(d) }]"
                  @click="toggleRemindDay(d)">
                  H-{{ d === 0 ? '0' : d }}
                </button>
              </div>
            </div>
            <button type="submit" class="btn-primary" :disabled="billForm.processing">
              {{ billForm.processing ? 'Menyimpan...' : 'Simpan Tagihan' }}
            </button>
          </form>
        </div>
      </div>

      <!-- ═══════════ MODAL: Bayar Tagihan ═══════════ -->
      <div v-if="showPayBill" class="modal-overlay" @click.self="showPayBill = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">Bayar {{ selectedBill?.name }}</div>
          <form @submit.prevent="submitPayBill">
            <div class="form-group">
              <label class="form-label">Bayar dari Dompet</label>
              <select v-model="payForm.wallet_id" class="form-input-cc" required>
                <option value="">Pilih dompet...</option>
                <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.display_name }} — {{ formatRupiah(w.balance) }}</option>
              </select>
            </div>
            <div class="form-group">
              <label class="form-label">Jumlah Dibayar (Rp)</label>
              <input v-model="payAmountDisplay" @input="onPayAmountInput" type="text" inputmode="numeric"
                class="form-input-cc amount-input" required />
            </div>
            <div class="form-group">
              <label class="form-label">Tanggal Bayar</label>
              <input v-model="payForm.paid_at" type="date" class="form-input-cc" required />
            </div>
            <button type="submit" class="btn-primary" :disabled="payForm.processing">
              {{ payForm.processing ? 'Memproses...' : '✅ Konfirmasi Bayar' }}
            </button>
          </form>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, computed, watch } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import EmojiPicker from '@/Components/EmojiPicker.vue'
import AppIcon from '@/Components/AppIcon.vue'

const props = defineProps({
  transactions: Object,
  wallets: Array,
  bills: Array,
  banks: Array,
  categories: Array,
  period: String,
  range: { type: String, default: 'today' },
  range_label: { type: String, default: 'Hari Ini' },
  total_income: Number,
  total_expense: Number,
  total_balance: Number,
  active_wallets_count: { type: Number, default: 0 },
  cash_total: { type: Number, default: 0 },
  bank_total: { type: Number, default: 0 },
  ewallet_total: { type: Number, default: 0 },
  search_query: String,
  active_tab: { type: String, default: 'transaksi' },
})

const balanceHidden = ref(false)
const showRangeMenu = ref(false)
const searchQuery = ref(props.search_query || '')

function changeRange(newRange) {
  showRangeMenu.value = false
  router.get(route('dompet.index'), { range: newRange, tab: 'transaksi' }, {
    preserveState: true, preserveScroll: true,
  })
}

function applySearch() {
  router.get(route('dompet.index'), { range: props.range, search: searchQuery.value, tab: 'transaksi' }, {
    preserveState: true, preserveScroll: true,
  })
}

const tab = ref(props.active_tab === 'in' || props.active_tab === 'out' ? 'transaksi' : (props.active_tab === 'bill' ? 'tagihan' : props.active_tab))

const showAddTx     = ref(false)
const showAddWallet = ref(false)
const showAddBill   = ref(false)
const showPayBill   = ref(false)
const editingTx      = ref(null)
const editingWallet  = ref(null)
const selectedBill   = ref(null)

const openAddForTab = () => {
  if (tab.value === 'transaksi') showAddTx.value = true
  else if (tab.value === 'dompet') showAddWallet.value = true
  else if (tab.value === 'tagihan') showAddBill.value = true
}

// ── Transaksi Form ──
const txForm = useForm({
  type: props.active_tab === 'out' ? 'expense' : 'income',
  amount: '', wallet_id: '', category_id: '', note: '',
  transacted_at: new Date().toISOString().split('T')[0],
})
const amountDisplay = ref('')

const onAmountInput = (e) => {
  const raw = e.target.value.replace(/\D/g, '')
  txForm.amount = raw
  amountDisplay.value = raw ? Number(raw).toLocaleString('id-ID') : ''
}

const filteredCategories = computed(() =>
  props.categories.filter(c => c.type === txForm.type)
)

const openEditTx = (t) => {
  editingTx.value = t
  txForm.type = t.type
  txForm.amount = t.amount
  amountDisplay.value = Number(t.amount).toLocaleString('id-ID')
  txForm.wallet_id = t.wallet_id
  txForm.category_id = t.category_id ?? ''
  txForm.note = t.note ?? ''
  txForm.transacted_at = t.transacted_at
  showAddTx.value = true
}

const closeTxModal = () => {
  showAddTx.value = false
  editingTx.value = null
  txForm.reset()
  amountDisplay.value = ''
}

const submitTx = () => {
  if (editingTx.value) {
    txForm.put(route('dompet.update', editingTx.value.id), {
      onSuccess: () => closeTxModal()
    })
  } else {
    txForm.post(route('dompet.store'), {
      onSuccess: () => closeTxModal()
    })
  }
}

const deleteTx = () => {
  if (!confirm('Hapus transaksi ini?')) return
  router.delete(route('dompet.destroy', editingTx.value.id), {
    onSuccess: () => closeTxModal()
  })
}

// ── Wallet Form ──
const walletForm = useForm({
  bank_id: '', display_name: '', initial_balance: '', type: 'both', is_active: true,
})

const setBankName = () => {
  if (!walletForm.bank_id) { walletForm.display_name = 'Cash'; return }
  const bank = props.banks.find(b => b.id == walletForm.bank_id)
  walletForm.display_name = bank?.short_name ?? ''
}

const openEditWallet = (w) => {
  editingWallet.value = w
  walletForm.display_name = w.display_name
  walletForm.type = w.type
  walletForm.is_active = true
  showAddWallet.value = true
}

const submitWallet = () => {
  if (editingWallet.value) {
    walletForm.put(route('wallets.update', editingWallet.value.id), {
      onSuccess: () => { showAddWallet.value = false; editingWallet.value = null }
    })
  } else {
    walletForm.post(route('wallets.store'), {
      onSuccess: () => { showAddWallet.value = false; walletForm.reset() }
    })
  }
}

const deleteWallet = () => {
  if (!editingWallet.value) return
  if (!confirm(`Hapus dompet "${editingWallet.value.display_name}"? Saldo harus 0 dulu.`)) return
  router.delete(route('wallets.destroy', editingWallet.value.id), {
    onSuccess: () => { showAddWallet.value = false; editingWallet.value = null }
  })
}

// ── Transfer Form ──
const showTransfer = ref(false)
const transferForm = useForm({
  from_wallet_id: '', to_wallet_id: '', amount: '', note: '',
  transferred_at: new Date().toISOString().split('T')[0],
})
const transferAmountDisplay = ref('')

const onTransferAmountInput = (e) => {
  const raw = e.target.value.replace(/\D/g, '')
  transferForm.amount = raw
  transferAmountDisplay.value = raw ? Number(raw).toLocaleString('id-ID') : ''
}

const submitTransfer = () => {
  transferForm.post(route('wallets.transfer'), {
    onSuccess: () => {
      showTransfer.value = false
      transferForm.reset()
      transferAmountDisplay.value = ''
    }
  })
}

// ── Bill Form ──
const billForm = useForm({
  name: '', emoji: '📋', amount: '', type: 'recurring',
  due_day: '', due_date: '', remind_days: [7, 1],
  notif_wa_enabled: true,
})
const billAmountDisplay = ref('')

const onBillAmountInput = (e) => {
  const raw = e.target.value.replace(/\D/g, '')
  billForm.amount = raw
  billAmountDisplay.value = raw ? Number(raw).toLocaleString('id-ID') : ''
}

const toggleRemindDay = (d) => {
  const idx = billForm.remind_days.indexOf(d)
  if (idx > -1) billForm.remind_days.splice(idx, 1)
  else billForm.remind_days.push(d)
}

const submitBill = () => {
  billForm.post(route('bills.store'), {
    onSuccess: () => {
      showAddBill.value = false
      billForm.reset()
      billAmountDisplay.value = ''
    }
  })
}

// ── Pay Bill Form ──
const payForm = useForm({
  wallet_id: '', amount_paid: '', paid_at: new Date().toISOString().split('T')[0],
})
const payAmountDisplay = ref('')

const onPayAmountInput = (e) => {
  const raw = e.target.value.replace(/\D/g, '')
  payForm.amount_paid = raw
  payAmountDisplay.value = raw ? Number(raw).toLocaleString('id-ID') : ''
}

const openPayBill = (b) => {
  selectedBill.value = b
  payForm.amount_paid = b.amount
  payAmountDisplay.value = Number(b.amount).toLocaleString('id-ID')
  showPayBill.value = true
}

const submitPayBill = () => {
  payForm.post(route('bills.pay', selectedBill.value.id), {
    onSuccess: () => { showPayBill.value = false }
  })
}

const typeLabel = (t) => ({ both: 'Multi Fungsi', cash_flow: 'Transaksi', saving: 'Tabungan' }[t] ?? t)
const dueLabel = (d) => {
  if (d === null) return 'Belum ada jadwal'
  if (d === 0) return '🔴 Jatuh tempo hari ini'
  if (d < 0) return `🔴 Terlambat ${Math.abs(d)} hari`
  if (d <= 3) return `🟡 H-${d}`
  return `🟢 H-${d}`
}
const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000) return (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)     return (n/1_000).toFixed(0) + 'rb'
  return String(n)
}
</script>

<style scoped>
.page-content { padding: 20px; }

.dompet-hero-bg {
  position: relative; overflow: hidden;
  background: linear-gradient(160deg, var(--primary) 0%, var(--primary-dark) 100%);
  margin: -20px -20px 0; padding: 20px 20px 24px;
  border-radius: 0 0 26px 26px;
}
.hero-top-row { display:flex; justify-content:space-between; align-items:flex-start; margin-bottom:14px; position:relative; z-index:2; }
.hero-page-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:22px; font-weight:800; color:white; }
.hero-page-sub { font-size:12px; color:rgba(255,255,255,.75); margin-top:4px; }
.hero-add-btn { width:44px; height:44px; border-radius:50%; background:white; color:var(--primary); border:none; font-size:20px; cursor:pointer; box-shadow:0 4px 12px rgba(0,0,0,.15); flex-shrink:0; }
.dompet-hero-illustration { position:absolute; right:14px; top:50px; width:80px; height:80px; opacity:.95; pointer-events:none; z-index:1; }
.hero-saldo-row { display:flex; align-items:center; gap:8px; position:relative; z-index:2; }
.hero-saldo-label { font-size:11px; font-weight:700; letter-spacing:.06em; color:rgba(255,255,255,.8); }
.hero-eye-btn { background:rgba(255,255,255,.18); border:none; border-radius:50%; width:26px; height:26px; cursor:pointer; display:flex; align-items:center; justify-content:center; color:white; }
.hero-eye-btn svg { width:14px; height:14px; }
.hero-saldo-amount { font-family:'Plus Jakarta Sans',sans-serif; font-size:28px; font-weight:800; color:white; margin:4px 0 10px; position:relative; z-index:2; }
.hidden-text { letter-spacing:.1em; color:rgba(255,255,255,.6); }
.hero-wallet-badge { display:inline-block; background:rgba(255,255,255,.18); color:white; font-size:11px; font-weight:600; padding:5px 12px; border-radius:99px; position:relative; z-index:2; }

.breakdown-card { display:flex; gap:14px; margin: -14px 0 16px; padding:16px; position:relative; z-index:3; }
.breakdown-item { flex:1; display:flex; gap:8px; align-items:flex-start; }
.bd-icon { width:32px; height:32px; border-radius:9px; display:flex; align-items:center; justify-content:center; font-size:15px; flex-shrink:0; }
.bd-icon.cash { background:var(--success-bg); }
.bd-icon.bank { background:var(--primary-bg); }
.bd-icon.ewallet { background:#F3E8FF; }
.bd-info { flex:1; min-width:0; }
.bd-label { font-size:10px; color:var(--text-secondary); font-weight:600; }
.bd-value { font-size:13px; font-weight:800; margin:2px 0 4px; }
.bd-value.cash { color:var(--success); }
.bd-value.bank { color:var(--primary); }
.bd-value.ewallet { color:#9333EA; }
.bd-bar-bg { height:4px; background:var(--background); border-radius:99px; overflow:hidden; }
.bd-bar-fill { height:100%; border-radius:99px; }
.bd-bar-fill.cash { background:var(--success); }
.bd-bar-fill.bank { background:var(--primary); }
.bd-bar-fill.ewallet { background:#9333EA; }

.range-filter-row { display:flex; align-items:center; gap:10px; flex-wrap:wrap; background:var(--surface); border-radius:var(--radius-lg); padding:12px 14px; box-shadow:var(--shadow-card); margin-bottom:12px; }
.range-dropdown { position:relative; }
.range-btn { background:var(--primary-bg); color:var(--primary); border:none; padding:8px 12px; border-radius:10px; font-size:12px; font-weight:700; cursor:pointer; display:flex; align-items:center; gap:4px; }
.range-menu { position:absolute; top:110%; left:0; background:var(--surface); border-radius:10px; box-shadow:var(--shadow-lg); z-index:50; overflow:hidden; min-width:130px; }
.range-menu button { display:block; width:100%; text-align:left; padding:10px 14px; background:none; border:none; font-size:12px; cursor:pointer; color:var(--text-primary); }
.range-menu button:hover { background:var(--background); }
.range-stat { display:flex; flex-direction:column; }
.rs-label { font-size:10px; color:var(--text-secondary); font-weight:600; }
.rs-val { font-size:13px; font-weight:800; }
.rs-val.up { color:var(--success); }
.rs-val.down { color:var(--danger); }

.search-row { display:flex; gap:8px; margin-bottom:16px; }
.search-box { flex:1; display:flex; align-items:center; gap:8px; background:var(--surface); border-radius:var(--radius-md); padding:10px 14px; box-shadow:var(--shadow-card); }
.search-box input { border:none; outline:none; background:none; font-size:13px; flex:1; font-family:inherit; }
.search-icon { font-size:14px; opacity:.6; }
.filter-btn { background:var(--surface); border:none; padding:10px 16px; border-radius:var(--radius-md); font-size:12px; font-weight:700; box-shadow:var(--shadow-card); cursor:pointer; white-space:nowrap; }

.tx-list-heading { margin-bottom:10px; }
.page-header {
  display:flex; justify-content:space-between; align-items:center;
  background:var(--surface); border-radius:var(--radius-lg); padding:16px 18px;
  margin-bottom:12px; box-shadow:var(--shadow-card);
  position:sticky; top:0; z-index:40;
}
.page-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:22px; font-weight:800; }
.add-icon-btn { width:38px; height:38px; border-radius:50%; background:var(--primary); color:white; border:none; font-size:18px; cursor:pointer; box-shadow:var(--shadow-sm); }

.tab-row { display:flex; gap:8px; margin-bottom:16px; }

.summary-row { display:flex; gap:10px; margin-bottom:16px; }
.summary-row.single { flex-direction:column; }
.summary-item { flex:1; background:var(--surface); border-radius:var(--radius-lg); padding:14px; box-shadow:var(--shadow-card); }
.summary-item.full { text-align:center; }
.summary-label { font-size:11px; color:var(--text-secondary); font-weight:600; margin-bottom:4px; }
.summary-val { font-family:'Plus Jakarta Sans',sans-serif; font-size:16px; font-weight:800; }
.summary-val.up { color:var(--success); }
.summary-val.down { color:var(--danger); }

.tx-list-card { padding:8px 16px; }
.tx-item { display:flex; align-items:center; gap:12px; padding:12px 0; border-bottom:1px solid var(--border); cursor:pointer; }
.tx-item:last-child { border-bottom:none; }
.tx-item:active { opacity:.7; }
.tx-icon { width:40px; height:40px; border-radius:12px; display:flex; align-items:center; justify-content:center; font-size:18px; flex-shrink:0; }
.tx-info { flex:1; min-width:0; }
.tx-name { font-size:13px; font-weight:600; white-space:nowrap; overflow:hidden; text-overflow:ellipsis; }
.tx-cat { font-size:11px; color:var(--text-secondary); }
.tx-amt { font-size:13px; font-weight:700; flex-shrink:0; }
.tx-amt.up { color:var(--success); }
.tx-amt.down { color:var(--danger); }

.empty-state { text-align:center; padding:32px 20px; }
.empty-illust { font-size:40px; margin-bottom:10px; }
.empty-text { font-size:14px; font-weight:600; color:var(--text-secondary); }

.wallet-card { margin-bottom:10px; cursor:pointer; }
.wallet-row { display:flex; align-items:center; gap:12px; }
.wallet-logo { width:44px; height:44px; border-radius:12px; color:white; font-weight:800; font-family:'Plus Jakarta Sans',sans-serif; font-size:12px; display:flex; align-items:center; justify-content:center; flex-shrink:0; overflow:hidden; }
.wallet-logo-img { width:100%; height:100%; object-fit:cover; }
.wallet-info { flex:1; }
.wallet-name { font-size:14px; font-weight:700; }
.wallet-type { font-size:11px; color:var(--text-secondary); }
.wallet-balance { font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; font-weight:800; }

.transfer-btn { width:100%; padding:12px; background:var(--primary-bg); color:var(--primary); border:none; border-radius:var(--radius-md); font-size:13px; font-weight:700; cursor:pointer; margin-bottom:12px; }
.danger-text { color:var(--danger) !important; border-color:var(--danger-bg) !important; }

.add-wallet-btn { width:100%; padding:16px; background:none; border:2px dashed var(--border); border-radius:var(--radius-lg); font-size:13px; font-weight:600; color:var(--text-secondary); cursor:pointer; margin-top:8px; display:flex; align-items:center; justify-content:center; gap:8px; transition:all .15s; }
.add-wallet-btn:hover { border-color:var(--primary); color:var(--primary); background:var(--primary-bg); }

.bill-card { margin-bottom:10px; }
.bill-row { display:flex; align-items:center; gap:12px; margin-bottom:10px; }
.bill-icon { font-size:24px; }
.bill-info { flex:1; }
.bill-name { font-size:14px; font-weight:700; }
.bill-due { font-size:11px; font-weight:600; margin-top:2px; }
.bill-amt { font-family:'Plus Jakarta Sans',sans-serif; font-size:14px; font-weight:800; }
.bill-pay-btn { width:100%; padding:10px; background:var(--primary-bg); color:var(--primary); border:none; border-radius:var(--radius-md); font-size:13px; font-weight:700; cursor:pointer; }

/* Modal */
.modal-overlay { position:fixed; inset:0; background:rgba(15,23,42,.45); z-index:500; display:flex; align-items:flex-end; justify-content:center; backdrop-filter:blur(4px); }
.modal-sheet { background:var(--surface); border-radius:28px 28px 0 0; width:100%; max-width:480px; padding:24px 20px 40px; max-height:90vh; overflow-y:auto; box-shadow:0 -10px 40px rgba(15,23,42,.15); }
.modal-handle { width:40px; height:4px; background:var(--border); border-radius:99px; margin:0 auto 20px; }
.modal-title { font-family:'Plus Jakarta Sans',sans-serif; font-size:18px; font-weight:800; margin-bottom:16px; }

.type-toggle { display:flex; gap:8px; margin-bottom:18px; }
.type-btn { flex:1; padding:12px; border-radius:var(--radius-md); border:1.5px solid var(--border); background:var(--surface); font-size:13px; font-weight:600; cursor:pointer; color:var(--text-secondary); }
.type-btn.active { border-color:var(--primary); background:var(--primary-bg); color:var(--primary); }

.form-group { margin-bottom:14px; }
.form-label { font-size:12px; font-weight:600; color:var(--text-secondary); display:block; margin-bottom:6px; }
.amount-input { font-family:'Plus Jakarta Sans',sans-serif; font-size:20px; font-weight:800; }

.remind-pills { display:flex; gap:8px; }
.remind-pill { padding:8px 14px; border-radius:99px; border:1.5px solid var(--border); background:var(--surface); font-size:12px; font-weight:600; color:var(--text-secondary); cursor:pointer; }
.remind-pill.selected { border-color:var(--primary); background:var(--primary); color:white; }
</style>
