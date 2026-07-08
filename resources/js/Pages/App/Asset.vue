<template>
  <AppLayout>
    <div class="page-content">

      <!-- Header -->
      <div class="page-header">
        <h1 class="page-title">Aset 💎</h1>
        <button class="add-btn" @click="showAddAsset = true">+ Tambah</button>
      </div>

      <div class="page-hint">
        <span class="page-hint-icon">💡</span>
        <span class="page-hint-text">Pantau estimasi kekayaan bersih dan dana darurat kamu.</span>
      </div>

      <!-- Estimasi Kekayaan Card -->
      <div class="kekayaan-card">
        <div class="kk-label">ESTIMASI KEKAYAAN</div>
        <div class="kk-total">{{ formatRupiah(total_kekayaan) }}</div>

        <!-- Level -->
        <div class="level-row">
          <div class="level-info">
            <div class="level-name">{{ level.name }}</div>
            <div class="level-next" v-if="level.next">
              Target berikutnya: {{ formatRupiah(level.next) }}
            </div>
          </div>
          <div class="level-badge">Lv {{ level.index }}/{{ level.total }}</div>
        </div>
        <div class="level-bar">
          <div class="level-fill" :style="`width:${level.percent}%`"></div>
        </div>
        <div class="level-pct">{{ level.percent }}% ke level berikutnya</div>

        <!-- Breakdown -->
        <div class="kk-breakdown">
          <div class="kb-item">
            <div class="kb-label">💵 Dompet & Akun</div>
            <div class="kb-val">{{ formatShort(wallet_total) }}</div>
          </div>
          <div class="kb-item" v-if="saham_total > 0">
            <div class="kb-label">📈 Saham</div>
            <div class="kb-val">{{ formatShort(saham_total) }}</div>
          </div>
          <div class="kb-item" v-if="invest_total > 0">
            <div class="kb-label">🏆 Investasi</div>
            <div class="kb-val">{{ formatShort(invest_total) }}</div>
          </div>
          <div class="kb-item" v-if="fixed_total > 0">
            <div class="kb-label">🏠 Aset Tetap</div>
            <div class="kb-val">{{ formatShort(fixed_total) }}</div>
          </div>
          <div class="kb-item" v-if="receivable_total > 0">
            <div class="kb-label">📋 Piutang</div>
            <div class="kb-val">{{ formatShort(receivable_total) }}</div>
          </div>
        </div>
      </div>

      <!-- Runway Dana Darurat -->
      <div class="runway-card">
        <div class="rw-header">
          <div>
            <div class="rw-title">🛡️ Runway Dana Darurat</div>
            <div class="rw-sub">Berapa lama bertahan tanpa pemasukan</div>
          </div>
          <div class="rw-months" v-if="runway_bulan !== null">
            <span class="rw-num">{{ runway_bulan }}</span>
            <span class="rw-unit">Bulan</span>
          </div>
          <div v-else class="rw-months">
            <span style="font-size:13px;color:var(--text-secondary)">Belum ada data</span>
          </div>
        </div>

        <div v-if="runway_bulan !== null">
          <div class="rw-bar">
            <div class="rw-fill"
              :class="runwayClass"
              :style="`width:${darurat_pct}%`">
            </div>
          </div>
          <div class="rw-meta">
            <span>{{ formatRupiah(liquid_total) }} dana likuid</span>
            <span :class="['rw-status', runwayClass]">
              {{ runwayLabel }}
            </span>
          </div>
          <div class="rw-target">
            Target: {{ darurat_target_bulan }} bulan
            ({{ formatRupiah(darurat_target_amount) }}) · {{ darurat_pct }}% tercapai
          </div>
        </div>
        <div v-else class="rw-hint">
          Mulai catat pengeluaran untuk menghitung runway dana darurat kamu.
        </div>
      </div>

      <!-- Dompet & Rekening -->
      <div class="sec-label">🏦 Dompet & Rekening</div>
      <div class="card">
        <div v-for="w in wallets" :key="w.id" class="asset-row">
          <div class="ar-logo" :style="`background:${w.bank_color}`">{{ w.bank_initial }}</div>
          <div class="ar-info">
            <div class="ar-name">{{ w.display_name }}</div>
            <div class="ar-type">{{ w.is_saham ? 'Portofolio Saham' : 'Rekening Bank' }}</div>
          </div>
          <div class="ar-val">{{ formatRupiah(w.balance) }}</div>
        </div>
      </div>

      <!-- Aset Tambahan -->
      <div class="sec-label" style="margin-top:18px;">📦 Aset Tambahan</div>

      <div v-if="assets.length === 0" class="empty-card">
        <div style="font-size:28px;margin-bottom:6px;">🏠</div>
        <div style="font-size:13px;color:var(--text-secondary);">Belum ada aset tambahan. Tambahkan properti, kendaraan, investasi, dll.</div>
      </div>

      <div v-for="asset in assets" :key="asset.id" class="card" style="margin-bottom:8px;">
        <div class="asset-row">
          <div class="ar-icon">{{ asset.emoji || typeIcon(asset.type) }}</div>
          <div class="ar-info">
            <div class="ar-name">{{ asset.name }}</div>
            <div class="ar-type">{{ typeLabel(asset.type) }}</div>
          </div>
          <div style="text-align:right;">
            <div class="ar-val">{{ formatRupiah(asset.value) }}</div>
            <button class="edit-sm" @click="openEdit(asset)">✏️ Edit</button>
          </div>
        </div>
      </div>

      <button class="add-asset-btn" @click="showAddAsset = true">
        <span style="font-size:22px;">＋</span>
        <span>Tambah Aset (properti, kendaraan, dll)</span>
      </button>

    </div>

    <!-- Add Asset Modal -->
    <Teleport to="body">
      <div v-if="showAddAsset" class="modal-overlay" @click.self="showAddAsset = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">Tambah Aset</div>
          <form @submit.prevent="submitAdd">
            <div class="form-group">
              <label class="form-label">Nama Aset</label>
              <input v-model="addForm.name" type="text" class="form-input-cc"
                placeholder="Rumah, Mobil, Emas 10gr..." required />
            </div>
            <div class="form-group">
              <label class="form-label">Tipe Aset</label>
              <div class="type-pills">
                <button v-for="t in assetTypes" :key="t.value" type="button"
                  :class="['type-pill', { selected: addForm.type === t.value }]"
                  @click="addForm.type = t.value">
                  {{ t.icon }} {{ t.label }}
                </button>
              </div>
            </div>
            <div class="form-group">
              <label class="form-label">Emoji (opsional)</label>
              <input v-model="addForm.emoji" type="text" class="form-input-cc"
                placeholder="🏠" style="width:80px;" />
            </div>
            <div class="form-group">
              <label class="form-label">Estimasi Nilai (Rp)</label>
              <input v-model="addForm.value" type="number" min="0" class="form-input-cc"
                style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;"
                placeholder="0" required />
            </div>
            <div class="form-group">
              <label class="form-label">Catatan (opsional)</label>
              <input v-model="addForm.note" type="text" class="form-input-cc"
                placeholder="Alamat, BPKB, keterangan..." />
            </div>
            <button type="submit" class="btn-cc" :disabled="addForm.processing">
              {{ addForm.processing ? 'Menyimpan...' : 'Tambah Aset' }}
            </button>
          </form>
        </div>
      </div>

      <!-- Edit Asset Modal -->
      <div v-if="showEditAsset" class="modal-overlay" @click.self="showEditAsset = false">
        <div class="modal-sheet">
          <div class="modal-handle"></div>
          <div class="modal-title">Edit Aset</div>
          <form @submit.prevent="submitEdit">
            <div class="form-group">
              <label class="form-label">Nama Aset</label>
              <input v-model="editForm.name" type="text" class="form-input-cc" required />
            </div>
            <div class="form-group">
              <label class="form-label">Estimasi Nilai (Rp)</label>
              <input v-model="editForm.value" type="number" min="0"
                class="form-input-cc" style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;" />
            </div>
            <div class="form-group">
              <label class="form-label">Catatan</label>
              <input v-model="editForm.note" type="text" class="form-input-cc" />
            </div>
            <button type="submit" class="btn-cc" :disabled="editForm.processing">Simpan</button>
            <button type="button" class="btn-cc"
              style="margin-top:10px;background:var(--red-light);color:var(--danger);"
              @click="deleteAsset">🗑️ Hapus Aset</button>
          </form>
        </div>
      </div>
    </Teleport>

  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'

const props = defineProps({
  wallets: Array,
  assets: Array,
  wallet_total: Number,
  saham_total: Number,
  liquid_total: Number,
  fixed_total: Number,
  invest_total: Number,
  receivable_total: Number,
  total_kekayaan: Number,
  runway_bulan: Number,
  avg_expense: Number,
  darurat_target_bulan: Number,
  darurat_target_amount: Number,
  darurat_pct: Number,
  level: Object,
})

const showAddAsset  = ref(false)
const showEditAsset = ref(false)
const selectedAsset = ref(null)

const assetTypes = [
  { value: 'liquid',     label: 'Likuid',    icon: '💵' },
  { value: 'fixed',      label: 'Tetap',     icon: '🏠' },
  { value: 'investment', label: 'Investasi', icon: '📈' },
  { value: 'receivable', label: 'Piutang',   icon: '📋' },
]

const runwayClass = computed(() => {
  if (props.runway_bulan === null) return 'muted'
  if (props.runway_bulan >= 6)  return 'green'
  if (props.runway_bulan >= 3)  return 'amber'
  return 'red'
})

const runwayLabel = computed(() => {
  if (props.runway_bulan === null) return '—'
  if (props.runway_bulan >= 6)  return '✅ Aman'
  if (props.runway_bulan >= 3)  return '⚠️ Cukup'
  return '🚨 Perlu ditingkatkan'
})

// Add form
const addForm = useForm({ name: '', emoji: '', type: 'fixed', value: '', note: '' })
const submitAdd = () => {
  addForm.post(route('asset.store'), {
    onSuccess: () => { showAddAsset.value = false; addForm.reset() }
  })
}

// Edit form
const editForm = useForm({ name: '', emoji: '', type: 'fixed', value: '', note: '' })
const openEdit = (asset) => {
  selectedAsset.value = asset
  editForm.name  = asset.name
  editForm.emoji = asset.emoji ?? ''
  editForm.type  = asset.type
  editForm.value = asset.value
  editForm.note  = asset.note ?? ''
  showEditAsset.value = true
}
const submitEdit = () => {
  editForm.put(route('asset.update', selectedAsset.value.id), {
    onSuccess: () => { showEditAsset.value = false }
  })
}
const deleteAsset = () => {
  if (!confirm('Hapus aset ini?')) return
  router.delete(route('asset.destroy', selectedAsset.value.id), {
    onSuccess: () => { showEditAsset.value = false }
  })
}

const typeLabel = (t) => ({ liquid:'Aset Likuid', fixed:'Aset Tetap', investment:'Investasi', receivable:'Piutang' }[t] ?? t)
const typeIcon  = (t) => ({ liquid:'💵', fixed:'🏠', investment:'📈', receivable:'📋' }[t] ?? '💰')
const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
const formatShort  = (n) => {
  n = Number(n || 0)
  if (n >= 1_000_000_000) return (n/1_000_000_000).toFixed(1) + 'M'
  if (n >= 1_000_000)     return (n/1_000_000).toFixed(1) + 'jt'
  if (n >= 1_000)         return (n/1_000).toFixed(0) + 'rb'
  return String(n)
}
</script>

<style scoped>
.page-content { padding:20px; }
.page-header { display:flex;justify-content:space-between;align-items:center;margin-bottom:16px; }
.page-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800; }
.add-btn { padding:8px 16px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;border:none;border-radius:99px;font-size:12px;font-weight:600;cursor:pointer; }

/* Kekayaan card */
.kekayaan-card { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);border-radius:var(--radius-xl);padding:22px;margin-bottom:12px;color:white; }
.kk-label { font-size:10px;font-weight:700;letter-spacing:.08em;text-transform:uppercase;color:rgba(255,255,255,.7);margin-bottom:6px; }
.kk-total { font-family:'Plus Jakarta Sans',sans-serif;font-size:26px;font-weight:800;letter-spacing:-.02em;margin-bottom:14px; }
.level-row { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:8px; }
.level-name { font-size:14px;font-weight:700;color:white; }
.level-next { font-size:10px;color:rgba(255,255,255,.35);margin-top:2px; }
.level-badge { font-size:11px;font-weight:700;background:rgba(255,255,255,.15);padding:3px 10px;border-radius:99px;color:rgba(255,255,255,.7);white-space:nowrap; }
.level-bar { height:5px;background:rgba(255,255,255,.15);border-radius:99px;overflow:hidden;margin-bottom:4px; }
.level-fill { height:100%;background:linear-gradient(90deg,#2ECC71,#27ae60);border-radius:99px;transition:width .6s ease; }
.level-pct { font-size:10px;color:rgba(255,255,255,.3);margin-bottom:14px; }
.kk-breakdown { display:flex;flex-wrap:wrap;gap:10px;padding-top:12px;border-top:1px solid rgba(255,255,255,.1); }
.kb-item { flex:1;min-width:80px; }
.kb-label { font-size:9px;color:rgba(255,255,255,.35);letter-spacing:.04em;text-transform:uppercase;margin-bottom:2px; }
.kb-val { font-size:13px;font-weight:700;color:rgba(255,255,255,.85); }

/* Runway */
.runway-card { background:var(--surface);border-radius:var(--radius-xl);padding:18px;box-shadow:var(--shadow-card);margin-bottom:12px; }
.rw-header { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:12px; }
.rw-title { font-size:13px;font-weight:700; }
.rw-sub { font-size:11px;color:var(--text-secondary);margin-top:2px; }
.rw-months { text-align:right; }
.rw-num { font-family:'Plus Jakarta Sans',sans-serif;font-size:26px;font-weight:800; }
.rw-unit { font-size:12px;color:var(--text-secondary);margin-left:4px; }
.rw-bar { height:8px;background:var(--background);border-radius:99px;overflow:hidden;margin-bottom:6px; }
.rw-fill { height:100%;border-radius:99px;transition:width .5s ease; }
.rw-fill.green { background:var(--success); }
.rw-fill.amber { background:var(--amber); }
.rw-fill.red   { background:var(--danger); }
.rw-meta { display:flex;justify-content:space-between;font-size:11px;color:var(--text-secondary);margin-bottom:6px; }
.rw-status { font-weight:700; }
.rw-status.green { color:var(--success); }
.rw-status.amber { color:#7a5a00; }
.rw-status.red   { color:var(--danger); }
.rw-target { font-size:11px;color:var(--text-secondary);line-height:1.5; }
.rw-hint { font-size:12px;color:var(--text-secondary);line-height:1.7;padding-top:4px; }

.sec-label { font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-secondary);margin:16px 0 10px; }
.card { background:var(--surface);border-radius:var(--radius-xl);padding:16px;box-shadow:var(--shadow-card); }
.asset-row { display:flex;align-items:center;gap:12px;padding:10px 0;border-bottom:1px solid var(--background); }
.asset-row:last-child { border-bottom:none; }
.ar-logo { width:36px;height:36px;border-radius:9px;color:white;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;font-size:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.ar-icon { width:36px;height:36px;border-radius:9px;background:var(--background);font-size:20px;display:flex;align-items:center;justify-content:center;flex-shrink:0; }
.ar-info { flex:1; }
.ar-name { font-size:13px;font-weight:600; }
.ar-type { font-size:11px;color:var(--text-secondary);margin-top:1px; }
.ar-val { font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800; }
.edit-sm { font-size:10px;background:var(--background);border:none;border-radius:6px;padding:3px 8px;cursor:pointer;margin-top:4px; }

.empty-card { background:var(--surface);border-radius:var(--radius-xl);padding:28px;text-align:center;box-shadow:var(--shadow-card);border:2px dashed var(--border);margin-bottom:12px; }

.add-asset-btn { display:flex;flex-direction:column;align-items:center;gap:6px;width:100%;padding:20px;border:2px dashed var(--border);border-radius:var(--radius-xl);background:none;cursor:pointer;font-size:13px;color:var(--text-secondary);font-weight:500;margin-top:4px;transition:all .2s; }
.add-asset-btn:hover { border-color:var(--text-primary);background:var(--surface);color:var(--text-primary); }

/* Modal */
.modal-overlay { position:fixed;inset:0;background:rgba(0,0,0,.5);z-index:500;display:flex;align-items:flex-end;justify-content:center;backdrop-filter:blur(4px); }
.modal-sheet { background:var(--surface);border-radius:20px 20px 0 0;width:100%;max-width:480px;padding:24px 20px 40px;max-height:90vh;overflow-y:auto;animation:slideUp .3s ease; }
@keyframes slideUp { from{transform:translateY(100%)} to{transform:translateY(0)} }
.modal-handle { width:40px;height:4px;background:var(--border);border-radius:99px;margin:0 auto 20px; }
.modal-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:18px;font-weight:800;margin-bottom:16px; }
.form-group { margin-bottom:14px; }
.form-label { font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px; }
.type-pills { display:flex;gap:8px;flex-wrap:wrap; }
.type-pill { padding:8px 14px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface);font-size:12px;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all .15s; }
.type-pill.selected { border-color:var(--text-primary);background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white; }
</style>
