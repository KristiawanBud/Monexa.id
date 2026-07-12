<template>
  <Teleport to="body">
    <Transition name="filter-overlay">
      <div v-if="open" class="filter-drawer-overlay" @click.self="$emit('close')"></div>
    </Transition>
    <Transition name="filter-panel">
      <div v-if="open" class="filter-drawer" role="dialog" aria-modal="true" aria-label="Filter transaksi">
        <div class="filter-drawer-handle"></div>
        <div class="filter-drawer-header">
          <span class="filter-drawer-title">Filter Transaksi</span>
          <button type="button" class="filter-drawer-close" @click="$emit('close')" aria-label="Tutup filter">✕</button>
        </div>

        <div class="filter-drawer-body">
          <div class="form-group">
            <label class="form-label">Dari Tanggal</label>
            <input v-model="form.start_date" type="date" class="form-input-cc" />
          </div>
          <div class="form-group">
            <label class="form-label">Sampai Tanggal</label>
            <input v-model="form.end_date" type="date" :min="form.start_date || undefined" class="form-input-cc" />
          </div>
          <div class="form-group">
            <label class="form-label">Dompet</label>
            <select v-model="form.wallet_id" class="form-input-cc">
              <option value="">Semua Dompet</option>
              <option v-for="w in wallets" :key="w.id" :value="w.id">{{ w.display_name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Kategori</label>
            <select v-model="form.category_id" class="form-input-cc">
              <option value="">Semua Kategori</option>
              <option v-for="c in categories" :key="c.id" :value="c.id">{{ c.emoji }} {{ c.name }}</option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Cari</label>
            <input v-model="form.search" type="text" class="form-input-cc" placeholder="Cari catatan transaksi..." />
          </div>
        </div>

        <div class="filter-drawer-actions">
          <button type="button" class="btn-secondary" @click="reset">Reset</button>
          <button type="button" class="btn-primary" @click="apply">Terapkan</button>
        </div>
      </div>
    </Transition>
  </Teleport>
</template>

<script setup>
import { reactive, watch } from 'vue'

const props = defineProps({
  open: { type: Boolean, default: false },
  wallets: { type: Array, default: () => [] },
  categories: { type: Array, default: () => [] },
  filters: {
    type: Object,
    default: () => ({ start_date: '', end_date: '', wallet_id: '', category_id: '', search: '' }),
  },
})

const emit = defineEmits(['apply', 'close'])

const emptyFilters = () => ({ start_date: '', end_date: '', wallet_id: '', category_id: '', search: '' })

const form = reactive({ ...emptyFilters(), ...props.filters })

watch(() => props.open, (isOpen) => {
  if (isOpen) Object.assign(form, emptyFilters(), props.filters)
})

const apply = () => {
  emit('apply', { ...form })
}

const reset = () => {
  Object.assign(form, emptyFilters())
  emit('apply', { ...form })
}
</script>

<style scoped>
.filter-drawer-overlay {
  position: fixed; inset: 0; background: rgba(15, 23, 42, .45);
  z-index: 600; backdrop-filter: blur(4px);
}

.filter-drawer {
  position: fixed;
  left: 50%;
  bottom: 0;
  transform: translateX(-50%);
  width: 100%;
  max-width: 480px;
  max-height: 85vh;
  background: var(--surface);
  border-radius: 28px 28px 0 0;
  padding: 16px 20px 24px;
  z-index: 601;
  box-shadow: 0 -10px 40px rgba(15, 23, 42, .15);
  display: flex;
  flex-direction: column;
}

.filter-drawer-handle { width: 40px; height: 4px; background: var(--border); border-radius: 99px; margin: 0 auto 16px; flex-shrink: 0; }

@media (min-width: 1024px) {
  .filter-drawer {
    left: auto;
    right: 0;
    top: 0;
    bottom: 0;
    transform: none;
    max-width: 360px;
    width: 100%;
    max-height: none;
    height: 100vh;
    border-radius: 0;
  }
  .filter-drawer-handle { display: none; }
}

.filter-drawer-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 16px; flex-shrink: 0; }
.filter-drawer-title { font-family: 'Plus Jakarta Sans', sans-serif; font-size: 16px; font-weight: 800; }
.filter-drawer-close { width: 44px; height: 44px; display: flex; align-items: center; justify-content: center; background: var(--background); border: none; border-radius: 50%; font-size: 14px; cursor: pointer; color: var(--text-secondary); }
.filter-drawer-close:focus-visible { box-shadow: var(--shadow-focus); outline: none; }

.filter-drawer-body { overflow-y: auto; flex: 1; }

.form-group { margin-bottom: 14px; }
.form-label { font-size: 12px; font-weight: 600; color: var(--text-secondary); display: block; margin-bottom: 6px; }

.filter-drawer-actions { display: flex; gap: 10px; margin-top: 8px; flex-shrink: 0; }
.filter-drawer-actions .btn-secondary,
.filter-drawer-actions .btn-primary { width: auto; flex: 1; }

.filter-panel-enter-active, .filter-panel-leave-active { transition: transform .3s cubic-bezier(.4, 0, .2, 1); }
.filter-panel-enter-from, .filter-panel-leave-to { transform: translateX(-50%) translateY(100%); }
@media (min-width: 1024px) {
  .filter-panel-enter-from, .filter-panel-leave-to { transform: translateX(100%); }
}
.filter-overlay-enter-active, .filter-overlay-leave-active { transition: opacity .3s ease; }
.filter-overlay-enter-from, .filter-overlay-leave-to { opacity: 0; }
</style>
