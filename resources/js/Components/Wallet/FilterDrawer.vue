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
        <span class="fd-label">Kategori Cepat</span>
        <CategoryChipFilter
          :categories="categories"
          :model-value="form.category_id || null"
          @select="(id) => (form.category_id = id || '')"
        />
      </div>

      <div class="fd-field">
        <span class="fd-label">Tipe</span>
        <div class="type-toggle fd-type-toggle">
          <button type="button" :class="['type-btn', { active: form.type === '' }]" @click="form.type = ''">Semua</button>
          <button type="button" :class="['type-btn', { active: form.type === 'income' }]" @click="form.type = 'income'">Masuk</button>
          <button type="button" :class="['type-btn', { active: form.type === 'expense' }]" @click="form.type = 'expense'">Keluar</button>
        </div>
      </div>

      <div class="fd-field">
        <label class="fd-label" for="fd-wallet">Dompet</label>
        <select id="fd-wallet" v-model="form.wallet_id" class="form-input-cc">
          <option value="">Semua Dompet</option>
          <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.display_name }}</option>
        </select>
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

const props = defineProps({
  open: { type: Boolean, default: false },
  wallets: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({ start_date: '', end_date: '', wallet_id: '', type: '', category_id: '' }),
  },
})
const emit = defineEmits(['update:open', 'apply'])

const form = reactive({
  start_date: props.filters.start_date || '',
  end_date: props.filters.end_date || '',
  wallet_id: props.filters.wallet_id || '',
  type: props.filters.type || '',
  category_id: props.filters.category_id || '',
})

watch(
  () => props.filters,
  (f) => {
    form.start_date = f.start_date || ''
    form.end_date = f.end_date || ''
    form.wallet_id = f.wallet_id || ''
    form.type = f.type || ''
    form.category_id = f.category_id || ''
  },
  { deep: true }
)

const handleApply = () => {
  emit('apply', { ...form })
  emit('update:open', false)
}

const handleReset = () => {
  form.start_date = ''
  form.end_date = ''
  form.wallet_id = ''
  form.type = ''
  form.category_id = ''
  emit('apply', { ...form })
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
.fd-type-toggle { margin-bottom: 0; }
.type-toggle { display: flex; gap: 8px; }
.type-btn { flex: 1; padding: 10px; min-height: 40px; border-radius: var(--radius-md); border: 1.5px solid var(--border); background: var(--surface); font-size: 12px; font-weight: 600; cursor: pointer; color: var(--text-secondary); }
.type-btn.active { border-color: var(--primary); background: var(--primary-bg); color: var(--primary); }

.fd-date-range { display: flex; gap: 10px; }
.fd-date-range > div { flex: 1; min-width: 0; }

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
  .fd-date-range { flex-direction: column; }
}
</style>
