<template>
  <AppLayout>
    <div class="page-content">

      <div class="page-header">
        <h1 class="page-title">Import Transaksi 📂</h1>
      </div>

      <div class="page-hint">
        <span class="page-hint-icon">💡</span>
        <span class="page-hint-text">Upload Excel/CSV dari bank atau e-wallet, AI bantu mapping otomatis.</span>
      </div>

      <div class="hint-box">
        📊 Upload file Excel/CSV dari app lain — AI akan baca format otomatis dan mapping kolom tanpa perlu setting manual.
      </div>

      <!-- Step indicator -->
      <div class="steps">
        <div :class="['step', step >= 1 ? 'done' : '', step === 1 ? 'active' : '']">
          <div class="step-num">1</div>
          <div class="step-label">Upload File</div>
        </div>
        <div class="step-line" :class="step >= 2 ? 'done' : ''"></div>
        <div :class="['step', step >= 2 ? 'done' : '', step === 2 ? 'active' : '']">
          <div class="step-num">2</div>
          <div class="step-label">Preview AI</div>
        </div>
        <div class="step-line" :class="step >= 3 ? 'done' : ''"></div>
        <div :class="['step', step >= 3 ? 'done' : '', step === 3 ? 'active' : '']">
          <div class="step-num">3</div>
          <div class="step-label">Konfirmasi</div>
        </div>
      </div>

      <!-- ── STEP 1: Upload ── -->
      <div v-if="step === 1">
        <div class="upload-area" @click="triggerUpload" @drop.prevent="handleDrop" @dragover.prevent>
          <div v-if="!selectedFile">
            <div style="font-size:40px;margin-bottom:10px;">📂</div>
            <div style="font-size:14px;font-weight:600;margin-bottom:4px;">Upload File Transaksi</div>
            <div style="font-size:12px;color:var(--text-secondary);">Excel (.xlsx, .xls) atau CSV · Maks 10MB</div>
          </div>
          <div v-else class="file-selected">
            <div style="font-size:32px;">📄</div>
            <div class="fs-name">{{ selectedFile.name }}</div>
            <div class="fs-size">{{ (selectedFile.size / 1024).toFixed(0) }} KB</div>
            <button class="change-btn" @click.stop="triggerUpload">Ganti File</button>
          </div>
          <input ref="fileInput" type="file" accept=".xlsx,.xls,.csv" style="display:none;" @change="handleFile" />
        </div>

        <!-- Template download -->
        <div class="template-section">
          <div class="ts-title">📥 Download Template</div>
          <div class="ts-list">
            <a :href="route('import.template', 'generic')" class="ts-item">
              <span>📋</span><span>Template Universal</span>
            </a>
            <a :href="route('import.template', 'bca')" class="ts-item">
              <span>🏦</span><span>BCA Mobile Export</span>
            </a>
            <a :href="route('import.template', 'mandiri')" class="ts-item">
              <span>🏦</span><span>Mandiri Online Export</span>
            </a>
            <a :href="route('import.template', 'gopay')" class="ts-item">
              <span>💚</span><span>GoPay / OVO / DANA</span>
            </a>
          </div>
        </div>

        <button class="btn-cc" @click="uploadFile" :disabled="!selectedFile || isUploading">
          {{ isUploading ? '🤖 AI Membaca File...' : 'Lanjut → AI Baca File' }}
        </button>
      </div>

      <!-- ── STEP 2: Preview ── -->
      <div v-if="step === 2 && previewData">
        <div class="ai-result-badge">
          🤖 AI Mendeteksi: <strong>{{ sourceLabel }}</strong> · {{ totalRows }} transaksi ditemukan
        </div>

        <div class="preview-card">
          <div class="pc-header">
            <div class="pc-title">Preview (20 baris pertama)</div>
            <div class="pc-sub">Periksa apakah data sudah benar sebelum import</div>
          </div>

          <div class="preview-table-wrap">
            <table class="preview-table">
              <thead>
                <tr>
                  <th>Tanggal</th>
                  <th>Keterangan</th>
                  <th>Tipe</th>
                  <th>Jumlah</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(row, i) in previewData" :key="i"
                  :class="row.type === 'income' ? 'income-row' : 'expense-row'">
                  <td>{{ row.date }}</td>
                  <td>{{ row.note }}</td>
                  <td>
                    <span :class="['type-badge', row.type]">
                      {{ row.type === 'income' ? '↑ Masuk' : '↓ Keluar' }}
                    </span>
                  </td>
                  <td class="amount-col">{{ formatRupiah(row.amount) }}</td>
                </tr>
              </tbody>
            </table>
          </div>

          <div v-if="totalRows > 20" class="preview-more">
            + {{ totalRows - 20 }} transaksi lagi akan diimport
          </div>
        </div>

        <div class="action-row">
          <button class="btn-secondary" @click="reset">← Upload Ulang</button>
          <button class="btn-primary" @click="step = 3">Lanjut →</button>
        </div>
      </div>

      <!-- ── STEP 3: Konfirmasi & Import ── -->
      <div v-if="step === 3">
        <div class="confirm-card">
          <div class="cc-title">⚙️ Pengaturan Import</div>

          <div class="form-group">
            <label class="form-label">Import ke Dompet</label>
            <div class="dompet-pills">
              <button v-for="w in wallets" :key="w.id" type="button"
                :class="['dompet-pill', { selected: selectedWallet === w.id }]"
                @click="selectedWallet = w.id">
                🏦 {{ w.display_name }}
              </button>
            </div>
          </div>

          <div class="import-summary">
            <div class="is-row">
              <span>File</span>
              <strong>{{ selectedFile?.name }}</strong>
            </div>
            <div class="is-row">
              <span>Format</span>
              <strong>{{ sourceLabel }}</strong>
            </div>
            <div class="is-row">
              <span>Total transaksi</span>
              <strong>{{ totalRows }}</strong>
            </div>
            <div class="is-row">
              <span>Dompet tujuan</span>
              <strong>{{ wallets.find(w => w.id === selectedWallet)?.display_name ?? '—' }}</strong>
            </div>
          </div>

          <div class="warn-box">
            ⚠️ Import tidak bisa dibatalkan. Pastikan kamu sudah memilih dompet yang benar dan file sudah sesuai.
          </div>
        </div>

        <div class="action-row">
          <button class="btn-secondary" @click="step = 2">← Preview</button>
          <button class="btn-primary" @click="confirmImport" :disabled="!selectedWallet || isImporting">
            {{ isImporting ? '⏳ Mengimport...' : `✅ Import ${totalRows} Transaksi` }}
          </button>
        </div>
      </div>

      <!-- ── STEP 4: Result ── -->
      <div v-if="step === 4 && importResult" class="result-card">
        <div class="result-icon">🎉</div>
        <div class="result-title">Import Selesai!</div>
        <div class="result-stats">
          <div class="rs-item green">
            <div class="rs-num">{{ importResult.imported }}</div>
            <div class="rs-label">Berhasil</div>
          </div>
          <div class="rs-item amber" v-if="importResult.skipped > 0">
            <div class="rs-num">{{ importResult.skipped }}</div>
            <div class="rs-label">Dilewati</div>
          </div>
          <div class="rs-item red" v-if="importResult.errors?.length > 0">
            <div class="rs-num">{{ importResult.errors.length }}</div>
            <div class="rs-label">Error</div>
          </div>
        </div>

        <div v-if="importResult.errors?.length" class="error-list">
          <div class="el-title">Detail Error:</div>
          <div v-for="e in importResult.errors" :key="e" class="el-item">{{ e }}</div>
        </div>

        <button class="btn-cc" @click="reset">Import File Lain</button>
        <Link :href="route('dompet.index')" class="btn-cc" style="margin-top:10px;background:var(--success);display:block;text-align:center;text-decoration:none;padding:14px;border-radius:var(--radius-md);color:white;font-weight:700;">
          Lihat Transaksi →
        </Link>
      </div>

      <!-- History -->
      <div v-if="history.length > 0 && step === 1">
        <div class="sec-label" style="margin-top:20px;">Riwayat Import</div>
        <div v-for="h in history" :key="h.id" class="history-row">
          <div class="hr-icon">📂</div>
          <div class="hr-info">
            <div class="hr-name">{{ h.filename }}</div>
            <div class="hr-meta">{{ h.source_label }} · {{ h.created_at }}</div>
          </div>
          <div class="hr-right">
            <span :class="['hr-status', h.status]">{{ statusLabel(h.status) }}</span>
            <div class="hr-count" v-if="h.status === 'done'">{{ h.imported_rows }} tx</div>
          </div>
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref } from 'vue'
import { Link, router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  history: Array,
  wallets: Array,
})

const step           = ref(1)
const fileInput      = ref(null)
const selectedFile   = ref(null)
const isUploading    = ref(false)
const isImporting    = ref(false)
const previewData    = ref(null)
const totalRows      = ref(0)
const sourceLabel    = ref('')
const sessionId      = ref(null)
const selectedWallet = ref(props.wallets[0]?.id ?? '')
const importResult   = ref(null)

const triggerUpload = () => fileInput.value?.click()

const handleFile = (e) => {
  const file = e.target.files?.[0]
  if (!file) return
  selectedFile.value = file
}

const handleDrop = (e) => {
  const file = e.dataTransfer.files?.[0]
  if (!file) return
  selectedFile.value = file
}

const uploadFile = async () => {
  if (!selectedFile.value) return
  isUploading.value = true

  const formData = new FormData()
  formData.append('file', selectedFile.value)

  try {
    const { data } = await axios.post(route('import.upload'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    if (data.success) {
      sessionId.value  = data.session_id
      previewData.value = data.preview
      totalRows.value  = data.total
      sourceLabel.value = data.source
      step.value = 2
    } else {
      alert(data.message)
    }
  } catch (err) {
    alert('Gagal membaca file. Pastikan format file sudah benar.')
  } finally {
    isUploading.value = false
  }
}

const confirmImport = async () => {
  if (!sessionId.value || !selectedWallet.value) return
  isImporting.value = true

  try {
    const { data } = await axios.post(route('import.confirm', sessionId.value), {
      wallet_id: selectedWallet.value,
    })

    if (data.success) {
      importResult.value = data
      step.value = 4
    }
  } catch {
    alert('Gagal mengimport. Coba lagi.')
  } finally {
    isImporting.value = false
  }
}

const reset = () => {
  step.value       = 1
  selectedFile.value = null
  previewData.value = null
  sessionId.value  = null
  importResult.value = null
  if (fileInput.value) fileInput.value.value = ''
}

const statusLabel = (s) => ({
  uploaded: '⬆️', parsing: '🔍', preview: '👀',
  importing: '⏳', done: '✅', failed: '❌'
}[s] ?? s)

const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
</script>

<style scoped>
.page-content { padding:20px; }
.page-header { margin-bottom:12px; }
.page-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800; }
.hint-box { background:var(--primary-bg);color:var(--primary-dark);border-radius:var(--radius-md);padding:12px 14px;font-size:12px;line-height:1.7;margin-bottom:16px; }

/* Steps */
.steps { display:flex;align-items:center;margin-bottom:20px; }
.step { display:flex;flex-direction:column;align-items:center;gap:4px;flex-shrink:0; }
.step-num { width:28px;height:28px;border-radius:50%;background:var(--background);color:var(--text-secondary);font-size:12px;font-weight:700;display:flex;align-items:center;justify-content:center;transition:all .2s; }
.step.active .step-num { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white; }
.step.done   .step-num { background:var(--success);color:white; }
.step-label { font-size:10px;font-weight:600;color:var(--text-secondary);white-space:nowrap; }
.step.active .step-label { color:var(--primary); }
.step-line { flex:1;height:2px;background:var(--background);margin:0 6px;transition:background .2s; }
.step-line.done { background:var(--success); }

/* Upload */
.upload-area { border:2px dashed var(--border);border-radius:var(--radius-xl);background:var(--surface);padding:32px;text-align:center;cursor:pointer;margin-bottom:14px;transition:all .2s; }
.upload-area:hover { border-color:var(--primary);background:var(--background); }
.file-selected { display:flex;flex-direction:column;align-items:center;gap:6px; }
.fs-name { font-size:13px;font-weight:600; }
.fs-size { font-size:11px;color:var(--text-secondary); }
.change-btn { margin-top:6px;padding:5px 14px;background:var(--background);border:none;border-radius:99px;font-size:11px;cursor:pointer; }

.template-section { background:var(--surface);border-radius:var(--radius-md);padding:14px;box-shadow:var(--shadow-card);margin-bottom:14px; }
.ts-title { font-size:12px;font-weight:700;margin-bottom:10px;color:var(--text-secondary); }
.ts-list { display:grid;grid-template-columns:1fr 1fr;gap:8px; }
.ts-item { display:flex;align-items:center;gap:8px;padding:8px 12px;background:var(--background);border-radius:var(--radius-md);text-decoration:none;color:var(--primary);font-size:12px;font-weight:500;transition:all .15s; }
.ts-item:hover { background:var(--background); }

/* Preview */
.ai-result-badge { background:var(--success-bg);color:var(--success);border-radius:var(--radius-md);padding:10px 14px;font-size:12px;margin-bottom:12px;line-height:1.6; }
.preview-card { background:var(--surface);border-radius:var(--radius-xl);box-shadow:var(--shadow-card);overflow:hidden;margin-bottom:14px; }
.pc-header { padding:14px 16px;border-bottom:1px solid var(--background); }
.pc-title { font-size:13px;font-weight:700; }
.pc-sub { font-size:11px;color:var(--text-secondary);margin-top:2px; }
.preview-table-wrap { overflow-x:auto; }
.preview-table { width:100%;border-collapse:collapse;font-size:12px; }
.preview-table th { padding:8px 12px;text-align:left;font-size:10px;font-weight:700;letter-spacing:.05em;text-transform:uppercase;color:var(--text-secondary);background:var(--background);border-bottom:1px solid var(--background); }
.preview-table td { padding:8px 12px;border-bottom:1px solid var(--background);white-space:nowrap; }
.income-row td { background:rgba(46,204,113,.04); }
.expense-row td { background:rgba(231,76,60,.03); }
.type-badge { padding:2px 7px;border-radius:99px;font-size:10px;font-weight:700; }
.type-badge.income { background:var(--success-bg);color:var(--success); }
.type-badge.expense { background:var(--danger-bg);color:var(--danger); }
.amount-col { font-family:'Plus Jakarta Sans',sans-serif;font-weight:700; }
.preview-more { padding:10px 16px;font-size:12px;color:var(--text-secondary);text-align:center;border-top:1px solid var(--background); }

/* Confirm */
.confirm-card { background:var(--surface);border-radius:var(--radius-xl);padding:18px;box-shadow:var(--shadow-card);margin-bottom:14px; }
.cc-title { font-size:14px;font-weight:700;margin-bottom:14px; }
.form-group { margin-bottom:14px; }
.form-label { font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px; }
.dompet-pills { display:flex;gap:8px;flex-wrap:wrap; }
.dompet-pill { padding:8px 14px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface);font-size:12px;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all .15s; }
.dompet-pill.selected { border-color:var(--primary);background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white; }
.import-summary { background:var(--background);border-radius:var(--radius-md);padding:12px;margin-bottom:14px; }
.is-row { display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid var(--background); }
.is-row:last-child { border-bottom:none; }
.warn-box { background:var(--amber-bg);border-radius:var(--radius-md);padding:10px 12px;font-size:12px;color:#7a5a00;line-height:1.6; }

/* Result */
.result-card { text-align:center;background:var(--surface);border-radius:var(--radius-xl);padding:28px 20px;box-shadow:var(--shadow-card);margin-bottom:16px; }
.result-icon { font-size:52px;margin-bottom:10px; }
.result-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:20px;font-weight:800;margin-bottom:16px; }
.result-stats { display:flex;gap:12px;justify-content:center;margin-bottom:16px; }
.rs-item { padding:10px 18px;border-radius:var(--radius-md);min-width:70px; }
.rs-item.green { background:var(--success-bg); }
.rs-item.amber { background:var(--amber-bg); }
.rs-item.red   { background:var(--danger-bg); }
.rs-num { font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:800; }
.rs-label { font-size:11px;color:var(--text-secondary); }
.error-list { text-align:left;background:var(--danger-bg);border-radius:var(--radius-md);padding:12px;margin-bottom:14px; }
.el-title { font-size:12px;font-weight:600;color:var(--danger);margin-bottom:6px; }
.el-item { font-size:11px;color:var(--danger);padding:3px 0; }

/* Actions */
.action-row { display:flex;gap:10px;margin-top:14px; }
.btn-primary { flex:1;padding:13px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;border:none;border-radius:var(--radius-md);font-size:14px;font-weight:700;cursor:pointer;font-family:inherit; }
.btn-primary:disabled { opacity:.5;cursor:not-allowed; }
.btn-secondary { flex:1;padding:13px;background:var(--background);color:var(--text-secondary);border:none;border-radius:var(--radius-md);font-size:13px;font-weight:600;cursor:pointer;font-family:inherit; }

.sec-label { font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:10px; }
.history-row { display:flex;align-items:center;gap:12px;background:var(--surface);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:8px;box-shadow:var(--shadow-card); }
.hr-icon { font-size:20px;flex-shrink:0; }
.hr-info { flex:1; }
.hr-name { font-size:13px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis; }
.hr-meta { font-size:11px;color:var(--text-secondary); }
.hr-right { text-align:right;flex-shrink:0; }
.hr-status { font-size:14px; }
.hr-count { font-size:11px;color:var(--success);font-weight:600; }
</style>
