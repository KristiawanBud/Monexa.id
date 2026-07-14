<template>
  <AppLayout>
    <div class="page-content">

      <BalanceSummaryCard
        :total-balance="total_balance"
        :active-wallets-count="active_wallets_count"
        :cash-total="cash_total"
        :bank-total="bank_total"
        :ewallet-total="ewallet_total"
        :balance-hidden="balanceHidden"
        :show-range-stats="tab === 'transaksi'"
        :total-income="total_income"
        :total-expense="total_expense"
        :range-label="range_label"
        @update:balance-hidden="balanceHidden = $event"
        @add="openAddForTab"
      />

      <!-- Tabs -->
      <div class="tab-row" role="tablist" aria-label="Bagian Dompet">
        <button role="tab" :aria-selected="tab === 'transaksi'" :class="['chip', { active: tab === 'transaksi' }]" @click="tab = 'transaksi'">Transaksi</button>
        <button role="tab" :aria-selected="tab === 'dompet'" :class="['chip', { active: tab === 'dompet' }]" @click="tab = 'dompet'">Dompet</button>
        <button role="tab" :aria-selected="tab === 'tagihan'" :class="['chip', { active: tab === 'tagihan' }]" @click="tab = 'tagihan'">Tagihan</button>
      </div>

      <div v-if="pullDistance > 0 || refreshing" class="pull-indicator" :style="`height:${refreshing ? 48 : pullDistance}px`">
        {{ refreshing ? '🔄 Memuat ulang...' : '↓ Tarik untuk refresh' }}
      </div>

      <!-- ═══════════ TAB: TRANSAKSI ═══════════ -->
      <div v-if="tab === 'transaksi'" class="tx-layout">
        <aside class="tx-sidebar">
          <div class="card range-summary-card">
            <div class="range-dropdown">
              <button class="range-btn" aria-haspopup="true" :aria-expanded="showRangeMenu" @click="showRangeMenu = !showRangeMenu">
                📅 {{ range_label }} <span class="range-caret">▾</span>
              </button>
              <div v-if="showRangeMenu" class="range-menu">
                <button @click="changeRange('today')">Hari Ini</button>
                <button @click="changeRange('week')">Minggu Ini</button>
                <button @click="changeRange('month')">Bulan Ini</button>
              </div>
            </div>
            <div class="range-stat-grid">
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
          </div>

          <FilterDrawer
            v-model:open="filterDrawerOpen"
            :wallets="wallets"
            :categories="categories"
            :filters="filters"
            @apply="applyFilters"
          />
        </aside>

        <div class="tx-main">
          <QuickActions
            :can-transfer="wallets.length >= 2"
            @add-income="openAddIncome"
            @add-expense="openAddExpense"
            @transfer="openTransfer"
          />

          <div class="search-row">
            <div class="search-box">
              <span class="search-icon">🔍</span>
              <input
                ref="searchInputRef"
                v-model="searchQuery"
                type="text"
                placeholder="Cari transaksi..."
                aria-label="Cari transaksi"
              />
            </div>
            <button class="filter-btn d-mobile-only" aria-label="Buka filter transaksi" @click="filterDrawerOpen = true">▤ Filter</button>
            <ExportButton :filters="exportFilters" />
          </div>

          <CategoryChipFilter
            :categories="categories"
            :model-value="filters.category_id || null"
            @select="onQuickCategorySelect"
          />

          <div class="tx-list-heading">
            <h2 class="section-title">Transaksi {{ range_label }}</h2>
          </div>

          <div class="card tx-list-card">
            <template v-if="isLoading">
              <SkeletonLoader v-for="n in 5" :key="n" variant="list-item" />
            </template>
            <ErrorState v-else-if="hasError" @retry="retryLoad" />
            <EmptyState
              v-else-if="!transactions.data || transactions.data.length === 0"
              icon="📝"
              title="Belum ada transaksi"
              action-label="+ Catat Transaksi"
              @action="showAddTx = true"
            />
            <template v-else>
              <TransactionDateGroup
                v-for="group in groupedTransactions"
                :key="group.key"
                :label="group.label"
                :transactions="group.transactions"
                @item-click="openEditTx"
                @cancel-transfer="cancelTransfer"
              />
            </template>
          </div>
        </div>
      </div>

      <!-- ═══════════ TAB: DOMPET ═══════════ -->
      <div v-if="tab === 'dompet'">

        <div class="summary-row single">
          <div class="summary-item full">
            <h2 class="summary-label">💰 Total Saldo Semua Dompet</h2>
            <div class="summary-val">{{ formatRupiah(total_balance) }}</div>
          </div>
        </div>

        <button v-if="wallets.length >= 2" class="transfer-btn" @click="openTransfer">
          🔄 Transfer Antar Dompet
        </button>

        <EmptyState
          v-if="wallets.length === 0"
          icon="👛"
          title="Belum ada dompet"
          action-label="+ Tambah Dompet"
          @action="showAddWallet = true"
        />

        <div v-else class="wallet-grid">
          <CardDompet
            v-for="w in wallets"
            :key="w.id"
            :wallet="w"
            :balance-hidden="balanceHidden"
            @click="openEditWallet"
          >
            <template #actions>
              <button type="button" class="wallet-action-btn" @click="archiveWallet(w)">
                Arsipkan
              </button>
            </template>
          </CardDompet>
        </div>

        <button class="add-wallet-btn" @click="showAddWallet = true">
          <span style="font-size:20px;">＋</span> Tambah Dompet Baru
        </button>
      </div>

      <!-- ═══════════ TAB: TAGIHAN ═══════════ -->
      <div v-if="tab === 'tagihan'">
        <h2 class="sr-only">Tagihan</h2>

        <EmptyState
          v-if="bills.length === 0"
          icon="📋"
          title="Belum ada tagihan"
          action-label="+ Tambah Tagihan"
          @action="showAddBill = true"
        />

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
            <div class="form-group">
              <label class="form-label">Ikon (opsional)</label>
              <EmojiPicker v-model="walletForm.icon" />
            </div>
            <div class="form-group">
              <label class="form-label">Warna (opsional)</label>
              <div class="wallet-color-swatches" role="radiogroup" aria-label="Pilih warna dompet">
                <button
                  v-for="c in walletColors"
                  :key="c"
                  type="button"
                  role="radio"
                  :aria-checked="walletForm.color === c"
                  :class="['color-swatch-btn', { active: walletForm.color === c }]"
                  :style="`background:var(--${c})`"
                  :aria-label="c"
                  @click="walletForm.color = walletForm.color === c ? '' : c"
                />
              </div>
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
import { ref, reactive, computed, watch, onMounted, onUnmounted } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import EmojiPicker from '@/Components/EmojiPicker.vue'
import BalanceSummaryCard from '@/Components/Wallet/BalanceSummaryCard.vue'
import QuickActions from '@/Components/Wallet/QuickActions.vue'
import CardDompet from '@/Components/Wallet/CardDompet.vue'
import TransactionDateGroup from '@/Components/Wallet/TransactionDateGroup.vue'
import FilterDrawer from '@/Components/Wallet/FilterDrawer.vue'
import CategoryChipFilter from '@/Components/Wallet/CategoryChipFilter.vue'
import EmptyState from '@/Components/Wallet/EmptyState.vue'
import ErrorState from '@/Components/Wallet/ErrorState.vue'
import SkeletonLoader from '@/Components/Wallet/SkeletonLoader.vue'
import ExportButton from '@/Components/Wallet/ExportButton.vue'
import { formatRupiah, formatShort } from '@/lib/format'
import { trackEvent } from '@/lib/analytics'

const props = defineProps({
  transactions: Object,
  wallets: Array,
  bills: Array,
  banks: Array,
  categories: Array,
  period: String,
  range: { type: String, default: 'today' },
  range_label: { type: String, default: 'Hari Ini' },
  start_date: { type: String, default: null },
  end_date: { type: String, default: null },
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
const searchInputRef = ref(null)
const filterDrawerOpen = ref(false)
const isLoading = ref(false)
const hasError = ref(false)
const FILTER_STORAGE_KEY = 'monexa_dompet_filters'

function readQueryFilters() {
  const params = new URLSearchParams(window.location.search)
  return {
    start_date: params.get('start_date') || '',
    end_date: params.get('end_date') || '',
    wallet_id: params.get('wallet_id') || '',
    type: params.get('type') || '',
    category_id: params.get('category_id') || '',
  }
}

const filters = reactive(readQueryFilters())

function buildQuery(extra = {}) {
  const hasCustomRange = (extra.start_date ?? filters.start_date) && (extra.end_date ?? filters.end_date)
  const base = {
    tab: 'transaksi',
    range: hasCustomRange ? undefined : props.range,
    start_date: filters.start_date || undefined,
    end_date: filters.end_date || undefined,
    wallet_id: filters.wallet_id || undefined,
    type: filters.type || undefined,
    category_id: filters.category_id || undefined,
    search: searchQuery.value || undefined,
  }
  return { ...base, ...extra }
}

function persistFilters(query) {
  window.localStorage.setItem(FILTER_STORAGE_KEY, JSON.stringify({
    range: query.range || '',
    start_date: query.start_date || '',
    end_date: query.end_date || '',
    wallet_id: query.wallet_id || '',
    type: query.type || '',
    category_id: query.category_id || '',
  }))
}

function reload(extra = {}) {
  const query = buildQuery(extra)
  Object.keys(query).forEach((k) => query[k] === undefined && delete query[k])
  persistFilters(query)
  router.get(route('dompet.index'), query, { preserveState: true, preserveScroll: true, replace: true })
}

function changeRange(newRange) {
  showRangeMenu.value = false
  filters.start_date = ''
  filters.end_date = ''
  reload({ range: newRange, start_date: undefined, end_date: undefined })
}

function applyFilters(payload) {
  Object.assign(filters, payload)
  trackEvent('dompet_filter_apply', payload)
  reload()
}

function onQuickCategorySelect(id) {
  filters.category_id = id || ''
  trackEvent('dompet_category_chip', { category_id: filters.category_id })
  reload()
}

function retryLoad() {
  hasError.value = false
  router.reload()
}

let searchDebounceTimer = null
watch(searchQuery, (val) => {
  clearTimeout(searchDebounceTimer)
  searchDebounceTimer = setTimeout(() => {
    trackEvent('dompet_search', { query: val })
    reload({ search: val || undefined })
  }, 400)
})

const exportFilters = computed(() => {
  const q = buildQuery()
  delete q.tab
  Object.keys(q).forEach((k) => q[k] === undefined && delete q[k])
  return q
})

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

const openAddIncome = () => {
  closeTxModal()
  txForm.type = 'income'
  showAddTx.value = true
  trackEvent('dompet_quick_action', { action: 'add-income' })
}

const openAddExpense = () => {
  closeTxModal()
  txForm.type = 'expense'
  showAddTx.value = true
  trackEvent('dompet_quick_action', { action: 'add-expense' })
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
const walletColors = ['primary', 'success', 'danger', 'warning', 'info']
const walletForm = useForm({
  bank_id: '', display_name: '', initial_balance: '', type: 'both', is_active: true,
  icon: '', color: '',
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
  walletForm.icon = w.icon || ''
  walletForm.color = w.color || ''
  showAddWallet.value = true
}

const archiveWallet = (w) => {
  router.patch(route('wallets.archive', w.id), {}, { preserveScroll: true })
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

const openTransfer = () => {
  showTransfer.value = true
  trackEvent('dompet_quick_action', { action: 'transfer' })
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

const cancelTransfer = (t) => {
  if (!confirm('Batalkan transfer ini? Saldo kedua dompet akan dikembalikan.')) return
  router.delete(route('wallets.transfer.destroy', t.transfer_id), { preserveScroll: true })
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

const dueLabel = (d) => {
  if (d === null) return 'Belum ada jadwal'
  if (d === 0) return '🔴 Jatuh tempo hari ini'
  if (d < 0) return `🔴 Terlambat ${Math.abs(d)} hari`
  if (d <= 3) return `🟡 H-${d}`
  return `🟢 H-${d}`
}

// ── Pengelompokan transaksi per tanggal (A.3) ──
function localYMD(date) {
  const y = date.getFullYear()
  const m = String(date.getMonth() + 1).padStart(2, '0')
  const d = String(date.getDate()).padStart(2, '0')
  return `${y}-${m}-${d}`
}
const todayYMD = localYMD(new Date())
const yesterdayYMD = localYMD(new Date(Date.now() - 86400000))

const groupedTransactions = computed(() => {
  const groups = []
  for (const t of props.transactions.data || []) {
    const last = groups[groups.length - 1]
    if (!last || last.key !== t.transacted_at) {
      let label = t.transacted_at_label
      if (t.transacted_at === todayYMD) label = 'Hari Ini'
      else if (t.transacted_at === yesterdayYMD) label = 'Kemarin'
      groups.push({ key: t.transacted_at, label, transactions: [t] })
    } else {
      last.transactions.push(t)
    }
  }
  return groups
})

// ── A.4a: Keyboard shortcut desktop (≥1025px) ──
function closeAllOverlays() {
  if (showAddTx.value) { closeTxModal(); return }
  if (showAddWallet.value) { showAddWallet.value = false; editingWallet.value = null; return }
  if (showTransfer.value) { showTransfer.value = false; return }
  if (showAddBill.value) { showAddBill.value = false; return }
  if (showPayBill.value) { showPayBill.value = false; return }
  if (filterDrawerOpen.value) { filterDrawerOpen.value = false; return }
  if (showRangeMenu.value) { showRangeMenu.value = false; return }
}

function onKeydown(e) {
  if (e.key === 'Escape') { closeAllOverlays(); return }
  if (window.innerWidth < 1025) return

  const tagName = document.activeElement?.tagName
  if (['INPUT', 'TEXTAREA', 'SELECT'].includes(tagName)) return

  if (e.key === '/') {
    e.preventDefault()
    searchInputRef.value?.focus()
  } else if (e.key === 'N' && e.shiftKey) {
    openAddExpense()
  } else if (e.key === 'n') {
    openAddIncome()
  } else if (e.key === 't' && props.wallets.length >= 2) {
    openTransfer()
  }
}

// ── A.4e: Pull-to-refresh (mobile only, ≤480px) ──
const pullDistance = ref(0)
const refreshing = ref(false)
let pullStartY = null

function onTouchStart(e) {
  if (window.innerWidth > 480 || window.scrollY > 0) { pullStartY = null; return }
  pullStartY = e.touches[0].clientY
}
function onTouchMove(e) {
  if (pullStartY === null) return
  const delta = e.touches[0].clientY - pullStartY
  if (delta > 0) pullDistance.value = Math.min(delta, 100)
}
function onTouchEnd() {
  if (pullStartY === null) return
  if (pullDistance.value > 60) {
    refreshing.value = true
    pullDistance.value = 0
    router.reload({ onFinish: () => { refreshing.value = false } })
  } else {
    pullDistance.value = 0
  }
  pullStartY = null
}

// ── A.9: Scroll depth transaksi (analytics stub) ──
let scrollTracked = false
function onWindowScroll() {
  if (scrollTracked || tab.value !== 'transaksi') return
  const percent = (window.scrollY + window.innerHeight) / document.documentElement.scrollHeight
  if (percent > 0.8) {
    scrollTracked = true
    trackEvent('dompet_tx_list_scroll_depth', { percent: 80 })
  }
}
watch(() => props.transactions?.data, () => { scrollTracked = false })

let removeStart, removeFinish, removeError

onMounted(() => {
  // A.4c: restore filter tersimpan kalau tidak ada query param eksplisit di URL
  const filterKeys = ['range', 'period', 'start_date', 'end_date', 'wallet_id', 'type', 'category_id', 'search']
  const params = new URLSearchParams(window.location.search)
  const hasExplicitFilter = filterKeys.some((k) => params.has(k))
  if (!hasExplicitFilter) {
    const saved = window.localStorage.getItem(FILTER_STORAGE_KEY)
    if (saved) {
      try {
        const parsed = JSON.parse(saved)
        const query = { tab: 'transaksi', ...parsed }
        Object.keys(query).forEach((k) => (!query[k]) && delete query[k])
        if (Object.keys(query).length > 1) {
          router.get(route('dompet.index'), query, { preserveState: true, preserveScroll: true, replace: true })
        }
      } catch { /* localStorage korup, abaikan */ }
    }
  }

  removeStart = router.on('start', () => { isLoading.value = true; hasError.value = false })
  removeFinish = router.on('finish', () => {
    isLoading.value = false
    Object.assign(filters, readQueryFilters())
  })
  removeError = router.on('error', () => { hasError.value = true })

  window.addEventListener('keydown', onKeydown)
  window.addEventListener('scroll', onWindowScroll, { passive: true })
  document.addEventListener('touchstart', onTouchStart, { passive: true })
  document.addEventListener('touchmove', onTouchMove, { passive: true })
  document.addEventListener('touchend', onTouchEnd, { passive: true })
})

onUnmounted(() => {
  removeStart?.()
  removeFinish?.()
  removeError?.()
  window.removeEventListener('keydown', onKeydown)
  window.removeEventListener('scroll', onWindowScroll)
  document.removeEventListener('touchstart', onTouchStart)
  document.removeEventListener('touchmove', onTouchMove)
  document.removeEventListener('touchend', onTouchEnd)
})
</script>

<style scoped>
.page-content { padding: 20px; }

.tab-row { display: flex; gap: 8px; margin-bottom: 16px; }

.pull-indicator {
  display: flex; align-items: center; justify-content: center;
  overflow: hidden; font-size: 12px; font-weight: 600; color: var(--text-secondary);
  transition: height .2s ease;
}

/* ── Grid 2 kolom tablet/desktop (≥481px): sidebar filter kiri, list kanan ── */
.tx-layout { display: block; }
.tx-sidebar { display: flex; flex-direction: column; gap: 16px; margin-bottom: 16px; }
.tx-main { min-width: 0; }

@media (min-width: 481px) {
  .tx-layout { display: grid; grid-template-columns: 280px 1fr; gap: 20px; align-items: start; }
  .tx-sidebar { margin-bottom: 0; }
  .tx-main { max-width: 720px; }
}

.range-summary-card { display: flex; flex-direction: column; gap: 14px; }
.range-dropdown { position: relative; }
.range-btn { background: var(--primary-bg); color: var(--primary); border: none; padding: 10px 14px; border-radius: 10px; font-size: 12px; font-weight: 700; cursor: pointer; display: flex; align-items: center; gap: 4px; min-height: 44px; width: 100%; justify-content: center; }
.range-menu { position: absolute; top: 110%; left: 0; right: 0; background: var(--surface); border-radius: 10px; box-shadow: var(--shadow-lg); z-index: 50; overflow: hidden; }
.range-menu button { display: block; width: 100%; text-align: left; padding: 12px 14px; background: none; border: none; font-size: 12px; cursor: pointer; color: var(--text-primary); min-height: 44px; }
.range-menu button:hover { background: var(--background); }
.range-stat-grid { display: flex; justify-content: space-between; gap: 8px; }
.range-stat { display: flex; flex-direction: column; }
.rs-label { font-size: 10px; color: var(--text-secondary); font-weight: 600; }
.rs-val { font-size: 13px; font-weight: 800; }
.rs-val.up { color: var(--success); }
.rs-val.down { color: var(--danger); }

.search-row { display: flex; gap: 8px; margin-bottom: 12px; }
.search-box { flex: 1; display: flex; align-items: center; gap: 8px; background: var(--surface); border-radius: var(--radius-md); padding: 10px 14px; box-shadow: var(--shadow-card); min-height: 44px; }
.search-box input { border: none; outline: none; background: none; font-size: 13px; flex: 1; font-family: inherit; }
.search-icon { font-size: 14px; opacity: .6; }
.filter-btn { background: var(--surface); border: none; padding: 10px 16px; border-radius: var(--radius-md); font-size: 12px; font-weight: 700; box-shadow: var(--shadow-card); cursor: pointer; white-space: nowrap; min-height: 44px; }
.d-mobile-only { }
@media (min-width: 481px) { .d-mobile-only { display: none; } }

.tx-list-heading { margin-bottom: 10px; }
.section-title { font-size: 15px; font-weight: 800; font-family: 'Plus Jakarta Sans', sans-serif; }

.tx-list-card { padding: 8px 16px; }

.sr-only { position: absolute; width: 1px; height: 1px; padding: 0; margin: -1px; overflow: hidden; clip: rect(0,0,0,0); white-space: nowrap; border: 0; }

.wallet-grid { display: block; }
@media (min-width: 481px) {
  .wallet-grid { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px; }
  .wallet-grid :deep(.wallet-card) { margin-bottom: 0; }
}
@media (min-width: 1025px) {
  .wallet-grid { grid-template-columns: repeat(3, 1fr); }
}

.summary-row { display: flex; gap: 10px; margin-bottom: 16px; }
.summary-row.single { flex-direction: column; }
.summary-item { flex: 1; background: var(--surface); border-radius: var(--radius-lg); padding: 14px; box-shadow: var(--shadow-card); }
.summary-item.full { text-align: center; }
.summary-label { font-size: 11px; color: var(--text-secondary); font-weight: 600; margin-bottom: 4px; }
.summary-val { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 800; }

.transfer-btn { width: 100%; padding: 12px; min-height: 44px; background: var(--primary-bg); color: var(--primary); border: none; border-radius: var(--radius-md); font-size: 13px; font-weight: 700; cursor: pointer; margin-bottom: 12px; }
.danger-text { color: var(--danger) !important; border-color: var(--danger-bg) !important; }

.wallet-action-btn { flex: 1; padding: 8px; min-height: 36px; background: var(--background); color: var(--text-secondary); border: 1.5px solid var(--border); border-radius: var(--radius-sm); font-size: 12px; font-weight: 600; cursor: pointer; }

.wallet-color-swatches { display: flex; gap: 10px; }
.color-swatch-btn { width: 32px; height: 32px; border-radius: 50%; border: 2px solid transparent; cursor: pointer; box-shadow: var(--shadow-sm); }
.color-swatch-btn.active { border-color: var(--text-primary); }

.add-wallet-btn { width: 100%; padding: 16px; min-height: 44px; background: none; border: 2px dashed var(--border); border-radius: var(--radius-lg); font-size: 13px; font-weight: 600; color: var(--text-secondary); cursor: pointer; margin-top: 8px; display: flex; align-items: center; justify-content: center; gap: 8px; transition: all .15s; }
.add-wallet-btn:hover { border-color: var(--primary); color: var(--primary); background: var(--primary-bg); }
.add-wallet-btn:focus-visible { outline: none; box-shadow: var(--shadow-focus); }

.bill-card { margin-bottom: 10px; }
.bill-row { display: flex; align-items: center; gap: 12px; margin-bottom: 10px; }
.bill-icon { font-size: 24px; }
.bill-info { flex: 1; }
.bill-name { font-size: 14px; font-weight: 700; }
.bill-due { font-size: 11px; font-weight: 600; margin-top: 2px; }
.bill-amt { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 14px; font-weight: 800; }
.bill-pay-btn { width: 100%; padding: 10px; min-height: 44px; background: var(--primary-bg); color: var(--primary); border: none; border-radius: var(--radius-md); font-size: 13px; font-weight: 700; cursor: pointer; }

/* Modal */
.modal-overlay { position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 500; display: flex; align-items: flex-end; justify-content: center; backdrop-filter: blur(4px); }
.modal-sheet { background: var(--surface); border-radius: 28px 28px 0 0; width: 100%; max-width: 480px; padding: 24px 20px 40px; max-height: 90vh; overflow-y: auto; box-shadow: 0 -10px 40px rgba(15,23,42,.15); }
.modal-handle { width: 40px; height: 4px; background: var(--border); border-radius: 99px; margin: 0 auto 20px; }
.modal-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 18px; font-weight: 800; margin-bottom: 16px; }

.type-toggle { display: flex; gap: 8px; margin-bottom: 18px; }
.type-btn { flex: 1; padding: 12px; min-height: 44px; border-radius: var(--radius-md); border: 1.5px solid var(--border); background: var(--surface); font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-secondary); }
.type-btn.active { border-color: var(--primary); background: var(--primary-bg); color: var(--primary); }

.form-group { margin-bottom: 14px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 6px; }
.amount-input { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 20px; font-weight: 800; }

.remind-pills { display: flex; gap: 8px; }
.remind-pill { padding: 8px 14px; min-height: 44px; border-radius: 99px; border: 1.5px solid var(--border); background: var(--surface); font-size: 12px; font-weight: 600; color: var(--text-secondary); cursor: pointer; }
.remind-pill.selected { border-color: var(--primary); background: var(--primary); color: white; }
</style>
