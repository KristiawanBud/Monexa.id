<template>
  <div class="filter-drawer" :class="{ 'is-open': open }">
    <div class="fd-overlay" @click="$emit('update:open', false)"></div>

    <div class="fd-panel" role="dialog" aria-label="Filter transaksi">
      <div class="fd-handle"></div>
      <div class="fd-header">
        <h2 class="fd-title">Filter</h2>
        <button type="button" class="fd-close" aria-label="Tutup filter" @click="$emit('update:open', false)">✕</button>
      </div>

      <div class="fd-field">
        <span class="fd-label">Kategori (multi-pilih)</span>
        <CategoryChipFilter
          :categories="categories"
          :model-value="form.category_id"
          @select="onCategorySelect"
        />
      </div>

      <div class="fd-field">
        <span class="fd-label">Tipe (minimal 1 dipilih)</span>
        <div class="type-checks" role="group" aria-label="Filter tipe transaksi">
          <button
            v-for="opt in typeOptions"
            :key="opt.value"
            type="button"
            :class="['type-check', { active: form.type.includes(opt.value) }]"
            :aria-pressed="form.type.includes(opt.value)"
            @click="toggleType(opt.value)"
          >
            <span class="type-check-box">{{ form.type.includes(opt.value) ? '✓' : '' }}</span>
            {{ opt.label }}
          </button>
        </div>
      </div>

      <div class="fd-field">
        <label class="fd-label" for="fd-wallet">Dompet</label>
        <select id="fd-wallet" v-model="form.wallet_id" class="form-input-cc">
          <option value="">Semua Dompet</option>
          <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.display_name }}</option>
        </select>
      </div>

      <div class="fd-field">
        <span class="fd-label">Rentang Cepat</span>
        <div class="date-presets">
          <button type="button" class="chip" @click="applyPreset('today')">Hari Ini</button>
          <button type="button" class="chip" @click="applyPreset('week')">Minggu Ini</button>
          <button type="button" class="chip" @click="applyPreset('month')">Bulan Ini</button>
        </div>
      </div>

      <div class="fd-field fd-date-range">
        <div>
          <label class="fd-label" for="fd-start">Dari Tanggal</label>
          <input id="fd-start" v-model="form.start_date" type="date" class="form-input-cc" :max="form.end_date || undefined" />
        </div>
        <div>
          <label class="fd-label" for="fd-end">Sampai Tanggal</label>
          <input id="fd-end" v-model="form.end_date" type="date" class="form-input-cc" :min="form.start_date || undefined" />
        </div>
      </div>

      <div class="fd-field fd-amount-range">
        <div>
          <label class="fd-label" for="fd-min-amount">Jumlah Min (Rp)</label>
          <input id="fd-min-amount" v-model="form.min_amount" type="number" inputmode="numeric" min="0" class="form-input-cc" placeholder="0" />
        </div>
        <div>
          <label class="fd-label" for="fd-max-amount">Jumlah Maks (Rp)</label>
          <input id="fd-max-amount" v-model="form.max_amount" type="number" inputmode="numeric" min="0" class="form-input-cc" placeholder="Tanpa batas" />
        </div>
      </div>

      <div class="fd-actions">
        <button type="button" class="btn-secondary" @click="handleReset">Reset</button>
        <button type="button" class="btn-primary" @click="handleApply">Terapkan Filter</button>
      </div>
    </div>
  </div>
</template>

<script setup>
import { reactive, watch } from 'vue'
import CategoryChipFilter from './CategoryChipFilter.vue'

const ALL_TYPES = ['income', 'expense', 'transfer']

const typeOptions = [
  { value: 'expense', label: 'Pengeluaran' },
  { value: 'income', label: 'Pemasukan' },
  { value: 'transfer', label: 'Transfer' },
]

const props = defineProps({
  open: { type: Boolean, default: false },
  wallets: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({
      start_date: '', end_date: '', wallet_id: '',
      type: ['income', 'expense', 'transfer'], category_id: [], min_amount: '', max_amount: '',
    }),
  },
})
const emit = defineEmits(['update:open', 'apply'])

function toArray(v, fallback) {
  if (Array.isArray(v)) return v.length ? [...v] : [...fallback]
  return v ? [v] : [...fallback]
}

const form = reactive({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  wallet_id: props.filters.wallet_id || '',
  type: toArray(props.filters.type, ALL_TYPES),
  category_id: toArray(props.filters.category_id, []),
  min_amount: props.filters.min_amount ?? '',
  max_amount: props.filters.max_amount ?? '',
})

watch(
  () => props.filters,
  (f) => {
    form.start_date = f.start_date || ''
    form.end_date = f.end_date || ''
    form.wallet_id = f.wallet_id || ''
    form.type = toArray(f.type, ALL_TYPES)
    form.category_id = toArray(f.category_id, [])
    form.min_amount = f.min_amount ?? ''
    form.max_amount = f.max_amount ?? ''
  },
  { deep: true }
)

function toggleType(value) {
  const idx = form.type.indexOf(value)
  if (idx > -1) {
    if (form.type.length === 1) return // minimal 1 tipe harus tetap terpilih
    form.type.splice(idx, 1)
  } else {
    form.type.push(value)
  }
}

function onCategorySelect(id) {
  if (id === null) { form.category_id = []; return }
  const idx = form.category_id.indexOf(id)
  if (idx > -1) form.category_id.splice(idx, 1)
  else form.category_id.push(id)
}

function pad(n) { return String(n).padStart(2, '0') }
function toYMD(d) { return `${d.getFullYear()}-${pad(d.getMonth() + 1)}-${pad(d.getDate())}` }

function applyPreset(preset) {
  const now = new Date()
  if (preset === 'today') {
    form.start_date = toYMD(now)
    form.end_date = toYMD(now)
  } else if (preset === 'week') {
    const dow = now.getDay() === 0 ? 7 : now.getDay()
    const monday = new Date(now)
    monday.setDate(now.getDate() - (dow - 1))
    const sunday = new Date(monday)
    sunday.setDate(monday.getDate() + 6)
    form.start_date = toYMD(monday)
    form.end_date = toYMD(sunday)
  } else if (preset === 'month') {
    const first = new Date(now.getFullYear(), now.getMonth(), 1)
    const last = new Date(now.getFullYear(), now.getMonth() + 1, 0)
    form.start_date = toYMD(first)
    form.end_date = toYMD(last)
  }
}

const handleApply = () => {
  emit('apply', { ...form, type: [...form.type], category_id: [...form.category_id] })
  emit('update:open', false)
}

const handleReset = () => {
  form.start_date = ''
  form.end_date = ''
  form.wallet_id = ''
  form.type = [...ALL_TYPES]
  form.category_id = []
  form.min_amount = ''
  form.max_amount = ''
  emit('apply', { ...form, type: [...form.type], category_id: [...form.category_id] })
  emit('update:open', false)
}
</script>

<style scoped>
.fd-overlay { display: none; }
.fd-panel { background: var(--surface); }

.fd-handle { display: none; }
.fd-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; }
.fd-close { display: none; width: 32px; height: 32px; border: none; background: var(--background); border-radius: 50%; font-size: 14px; cursor: pointer; min-width: 44px; min-height: 44px; }
.fd-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 800; }

.fd-field { margin-bottom: 16px; }
.fd-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 6px; }

.type-checks { display: flex; flex-direction: column; gap: 8px; }
.type-check {
  display: flex; align-items: center; gap: 10px;
  padding: 10px 12px; min-height: 44px; border-radius: var(--radius-md);
  border: 1.5px solid var(--border); background: var(--surface);
  font-size: 13px; font-weight: 600; cursor: pointer; color: var(--text-secondary);
  text-align: left; font-family: inherit;
}
.type-check.active { border-color: var(--primary); background: var(--primary-bg); color: var(--primary); }
.type-check-box {
  width: 18px; height: 18px; border-radius: 5px; border: 1.5px solid var(--border);
  display: inline-flex; align-items: center; justify-content: center; font-size: 12px;
  flex-shrink: 0; background: var(--surface);
}
.type-check.active .type-check-box { border-color: var(--primary); background: var(--primary); color: white; }

.date-presets { display: flex; gap: 8px; flex-wrap: wrap; }

.fd-date-range, .fd-amount-range { display: flex; gap: 10px; }
.fd-date-range > div, .fd-amount-range > div { flex: 1; min-width: 0; }

.fd-actions { display: flex; gap: 10px; }
.fd-actions .btn-secondary, .fd-actions .btn-primary { width: auto; flex: 1; }

/* ── Mobile (<481px): bottom sheet ── */
@media (max-width: 480px) {
  .fd-overlay {
    display: none;
    position: fixed; inset: 0; background: rgba(15,23,42,.45); z-index: 300; backdrop-filter: blur(4px);
  }
  .filter-drawer.is-open .fd-overlay { display: block; }

  .fd-panel {
    position: fixed; left: 50%; bottom: -100%; transform: translateX(-50%);
    width: 100%; max-width: 480px;
    border-radius: 28px 28px 0 0;
    padding: 20px 20px 32px;
    z-index: 301;
    max-height: 85vh;
    overflow-y: auto;
    box-shadow: 0 -10px 40px rgba(15,23,42,.15);
    transition: bottom .3s cubic-bezier(.4,0,.2,1);
  }
  .filter-drawer.is-open .fd-panel { bottom: 0; }
  .fd-handle { display: block; width: 40px; height: 4px; background: var(--border); border-radius: 99px; margin: 0 auto 16px; }
  .fd-close { display: block; }
}

/* ── Tablet & Desktop (≥481px): panel sisi sticky ── */
@media (min-width: 481px) {
  .fd-panel {
    border-radius: var(--radius-lg);
    box-shadow: var(--shadow-card);
    padding: 18px;
    position: sticky;
    top: 24px;
  }
  .fd-date-range, .fd-amount-range { flex-direction: column; }
}
</style>
