<template>
  <AppLayout>
    <div class="page-content">

      <div class="page-header">
        <h1 class="page-title">Scan Struk 📷</h1>
      </div>

      <div class="page-hint">
        <span class="page-hint-icon">💡</span>
        <span class="page-hint-text">Foto struk belanja, AI akan baca dan catat otomatis untukmu.</span>
      </div>

      <div class="hint-box">
        📸 Foto struk belanja → AI (Gemini) baca otomatis → konfirmasi → langsung tercatat!
      </div>

      <!-- Upload area -->
      <div class="upload-area" @click="triggerUpload" @drop.prevent="handleDrop" @dragover.prevent>
        <div v-if="!previewUrl" class="upload-placeholder">
          <div style="font-size:48px;margin-bottom:12px;">📷</div>
          <div style="font-size:14px;font-weight:600;margin-bottom:4px;">Foto atau Upload Struk</div>
          <div style="font-size:12px;color:var(--text-secondary);">Klik di sini atau drag & drop gambar</div>
          <div style="font-size:11px;color:var(--border);margin-top:8px;">JPG, PNG · Maks 5MB</div>
        </div>
        <div v-else class="preview-wrap">
          <img :src="previewUrl" class="preview-img" />
          <button class="change-btn" @click.stop="triggerUpload">Ganti Foto</button>
        </div>
        <input ref="fileInput" type="file" accept="image/*" capture="environment"
          style="display:none;" @change="handleFile" />
      </div>

      <!-- Scan button -->
      <button class="btn-cc" :disabled="!selectedFile || isScanning" @click="scanReceipt"
        style="margin-bottom:20px;">
        <span v-if="isScanning">🔍 Membaca struk...</span>
        <span v-else>🤖 Baca Struk dengan AI</span>
      </button>

      <!-- Parse result -->
      <div v-if="scanResult" class="result-card">
        <div class="result-header">
          <div>
            <div class="result-title">✅ Struk Terbaca!</div>
            <div class="result-sub">Periksa & edit jika perlu, lalu konfirmasi</div>
          </div>
          <div class="confidence-badge" :class="confidenceClass">
            {{ Math.round((scanResult.confidence || 0) * 100) }}% akurat
          </div>
        </div>

        <div class="result-info">
          <div class="ri-row">
            <span class="ri-label">🏪 Merchant</span>
            <span class="ri-val">{{ scanResult.merchant || '—' }}</span>
          </div>
          <div class="ri-row" v-if="scanResult.date">
            <span class="ri-label">📅 Tanggal</span>
            <span class="ri-val">{{ scanResult.date }}</span>
          </div>
          <div class="ri-row">
            <span class="ri-label">💳 Total</span>
            <span class="ri-val" style="font-family:'Syne',sans-serif;font-size:18px;font-weight:800;">
              {{ formatRupiah(scanResult.total) }}
            </span>
          </div>
        </div>

        <!-- Items dari struk -->
        <div v-if="scanResult.items?.length" class="items-list">
          <div style="font-size:11px;font-weight:700;color:var(--text-secondary);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px;">Item Dibeli</div>
          <div v-for="item in scanResult.items" :key="item.name" class="item-row">
            <span class="item-name">{{ item.name }}</span>
            <span class="item-price">{{ formatRupiah(item.price) }}</span>
          </div>
        </div>

        <!-- Konfirmasi form -->
        <div style="border-top:1px solid var(--background);padding-top:16px;margin-top:16px;">
          <div class="form-group">
            <label class="form-label">Nominal (Rp)</label>
            <input v-model="confirmForm.amount" type="number" class="form-input-cc"
              style="font-family:'Syne',sans-serif;font-size:20px;font-weight:800;" />
          </div>
          <div class="form-group">
            <label class="form-label">Bayar dari Dompet</label>
            <div class="dompet-pills">
              <button v-for="w in wallets" :key="w.id" type="button"
                :class="['dompet-pill', { selected: confirmForm.wallet_id === w.id }]"
                @click="confirmForm.wallet_id = w.id">
                🏦 {{ w.display_name }}
              </button>
            </div>
          </div>
          <div class="form-group">
            <label class="form-label">Kategori</label>
            <select v-model="confirmForm.category_id" class="form-input-cc">
              <option value="">Pilih kategori...</option>
              <option v-for="cat in categories" :key="cat.id" :value="cat.id">
                {{ cat.emoji }} {{ cat.name }}
              </option>
            </select>
          </div>
          <div class="form-group">
            <label class="form-label">Catatan</label>
            <input v-model="confirmForm.note" type="text" class="form-input-cc"
              :placeholder="scanResult.merchant || 'Catatan...'" />
          </div>
          <div class="form-group">
            <label class="form-label">Tanggal</label>
            <input v-model="confirmForm.transacted_at" type="date" class="form-input-cc" />
          </div>
          <button class="btn-cc green" @click="confirmScan" :disabled="isConfirming">
            {{ isConfirming ? 'Menyimpan...' : '✅ Konfirmasi & Simpan Transaksi' }}
          </button>
          <button class="btn-cc" style="margin-top:10px;background:var(--background);color:var(--text-secondary);"
            @click="resetScan">
            🔄 Scan Struk Lain
          </button>
        </div>
      </div>

      <!-- Riwayat scan -->
      <div class="sec-label" style="margin-top:20px;">Riwayat Scan Terakhir</div>
      <div v-if="scans.length === 0" class="empty-card">
        <div style="font-size:13px;color:var(--text-secondary);">Belum ada riwayat scan</div>
      </div>
      <div v-for="scan in scans" :key="scan.id" class="scan-history-row">
        <div class="sh-icon">
          <span :class="['sh-status', scan.status]">
            {{ scan.status === 'confirmed' ? '✅' : scan.status === 'parsed' ? '🔍' : scan.status === 'failed' ? '❌' : '⏳' }}
          </span>
        </div>
        <div class="sh-info">
          <div class="sh-merchant">{{ scan.parsed_result?.merchant ?? 'Struk' }}</div>
          <div class="sh-meta">{{ scan.created_at }} · {{ statusLabel(scan.status) }}</div>
        </div>
        <div class="sh-total" v-if="scan.parsed_result?.total">
          {{ formatRupiah(scan.parsed_result.total) }}
        </div>
      </div>

    </div>
  </AppLayout>
</template>

<script setup>
import { ref, computed } from 'vue'
import { router } from '@inertiajs/vue3'
import AppLayout from '@/Layouts/AppLayout.vue'
import axios from 'axios'

const props = defineProps({
  scans: Array,
  wallets: Array,
  categories: Array,
})

const fileInput   = ref(null)
const selectedFile = ref(null)
const previewUrl  = ref(null)
const isScanning  = ref(false)
const isConfirming = ref(false)
const scanResult  = ref(null)
const scanId      = ref(null)

const confirmForm = ref({
  wallet_id:     props.wallets[0]?.id ?? '',
  amount:        '',
  category_id:   '',
  note:          '',
  transacted_at: new Date().toISOString().split('T')[0],
})

const triggerUpload = () => fileInput.value?.click()

const handleFile = (e) => {
  const file = e.target.files?.[0]
  if (!file) return
  selectedFile.value = file
  previewUrl.value   = URL.createObjectURL(file)
  scanResult.value   = null
}

const handleDrop = (e) => {
  const file = e.dataTransfer.files?.[0]
  if (!file || !file.type.startsWith('image/')) return
  selectedFile.value = file
  previewUrl.value   = URL.createObjectURL(file)
  scanResult.value   = null
}

const scanReceipt = async () => {
  if (!selectedFile.value) return
  isScanning.value = true

  const formData = new FormData()
  formData.append('receipt', selectedFile.value)

  try {
    const resp = await axios.post(route('receipt.upload'), formData, {
      headers: { 'Content-Type': 'multipart/form-data' }
    })

    if (resp.data.success) {
      scanId.value     = resp.data.scan_id
      scanResult.value = resp.data.data
      confirmForm.value.amount = resp.data.data.total
      confirmForm.value.note   = resp.data.data.merchant ?? ''
      if (resp.data.data.date) {
        confirmForm.value.transacted_at = resp.data.data.date
      }
    } else {
      alert(resp.data.message)
    }
  } catch (err) {
    alert('Gagal membaca struk. Coba foto yang lebih jelas.')
  } finally {
    isScanning.value = false
  }
}

const confirmScan = async () => {
  if (!scanId.value || !confirmForm.value.wallet_id) return
  isConfirming.value = true

  router.post(route('receipt.confirm', scanId.value), confirmForm.value, {
    onSuccess: () => { resetScan() },
    onFinish:  () => { isConfirming.value = false },
  })
}

const resetScan = () => {
  selectedFile.value = null
  previewUrl.value   = null
  scanResult.value   = null
  scanId.value       = null
  confirmForm.value  = {
    wallet_id: props.wallets[0]?.id ?? '',
    amount: '', category_id: '', note: '',
    transacted_at: new Date().toISOString().split('T')[0],
  }
}

const confidenceClass = computed(() => {
  const c = scanResult.value?.confidence ?? 0
  return c >= 0.8 ? 'green' : c >= 0.5 ? 'amber' : 'red'
})

const statusLabel = (s) => ({ pending:'Menunggu', parsed:'Terbaca', confirmed:'Tersimpan', failed:'Gagal' }[s] ?? s)
const formatRupiah = (n) => 'Rp ' + Number(n || 0).toLocaleString('id-ID')
</script>

<style scoped>
.page-content { padding:20px; }
.page-header { margin-bottom:12px; }
.page-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:22px;font-weight:800; }
.hint-box { background:var(--primary-bg);color:var(--primary-dark);border-radius:var(--radius-md);padding:12px 14px;font-size:12px;line-height:1.7;margin-bottom:16px; }

.upload-area { border:2px dashed var(--border);border-radius:var(--radius-xl);background:var(--surface);cursor:pointer;margin-bottom:14px;min-height:180px;display:flex;align-items:center;justify-content:center;transition:all .2s; }
.upload-area:hover { border-color:var(--primary);background:var(--background); }
.upload-placeholder { text-align:center;padding:32px; }
.preview-wrap { width:100%;position:relative;text-align:center; }
.preview-img { max-width:100%;max-height:260px;border-radius:12px;object-fit:contain; }
.change-btn { position:absolute;bottom:12px;right:12px;padding:6px 14px;background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white;border:none;border-radius:99px;font-size:11px;font-weight:700;cursor:pointer; }

/* Result card */
.result-card { background:var(--surface);border-radius:var(--radius-xl);padding:18px;box-shadow:var(--shadow-card);margin-bottom:16px; }
.result-header { display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:14px; }
.result-title { font-size:14px;font-weight:700; }
.result-sub { font-size:11px;color:var(--text-secondary);margin-top:2px; }
.confidence-badge { font-size:11px;font-weight:700;padding:4px 10px;border-radius:99px; }
.confidence-badge.green { background:var(--success-bg);color:var(--success); }
.confidence-badge.amber { background:var(--amber-bg);color:#7a5a00; }
.confidence-badge.red   { background:var(--danger-bg);color:var(--danger); }
.result-info { background:var(--background);border-radius:var(--radius-md);padding:12px;margin-bottom:12px; }
.ri-row { display:flex;justify-content:space-between;align-items:center;padding:6px 0;border-bottom:1px solid var(--background); }
.ri-row:last-child { border-bottom:none; }
.ri-label { font-size:12px;color:var(--text-secondary); }
.ri-val { font-size:13px;font-weight:600; }
.items-list { margin-bottom:14px; }
.item-row { display:flex;justify-content:space-between;font-size:12px;padding:5px 0;border-bottom:1px solid var(--background); }
.item-row:last-child { border-bottom:none; }
.item-name { color:var(--text-secondary); }
.item-price { font-weight:600; }
.form-group { margin-bottom:12px; }
.form-label { font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px; }
.dompet-pills { display:flex;gap:8px;flex-wrap:wrap; }
.dompet-pill { padding:8px 14px;border-radius:99px;border:1.5px solid var(--border);background:var(--surface);font-size:12px;font-weight:600;color:var(--text-secondary);cursor:pointer;transition:all .15s; }
.dompet-pill.selected { border-color:var(--primary);background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%);color:white; }

/* History */
.sec-label { font-size:11px;font-weight:700;letter-spacing:.07em;text-transform:uppercase;color:var(--text-secondary);margin-bottom:10px; }
.empty-card { background:var(--surface);border-radius:var(--radius-md);padding:20px;text-align:center;box-shadow:var(--shadow-card); }
.scan-history-row { display:flex;align-items:center;gap:12px;background:var(--surface);border-radius:var(--radius-md);padding:12px 14px;margin-bottom:8px;box-shadow:var(--shadow-card); }
.sh-icon { width:36px;height:36px;display:flex;align-items:center;justify-content:center;font-size:20px;flex-shrink:0; }
.sh-info { flex:1; }
.sh-merchant { font-size:13px;font-weight:600; }
.sh-meta { font-size:11px;color:var(--text-secondary);margin-top:1px; }
.sh-total { font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;font-weight:800;flex-shrink:0; }
</style>
