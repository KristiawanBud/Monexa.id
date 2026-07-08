<template>
  <div class="onboard-page">
    <div class="onboard-progress">
      <div class="step-label">Langkah 2 dari 3</div>
      <div class="step-bars">
        <div class="step-bar active"></div>
        <div class="step-bar active"></div>
        <div class="step-bar"></div>
      </div>
    </div>

    <div class="onboard-body">
      <div class="onboard-icon">🏦</div>
      <h1 class="onboard-title">Tambahkan dompetmu</h1>
      <p class="onboard-sub">Pilih bank atau dompet yang kamu pakai sehari-hari.</p>

      <!-- Added wallets -->
      <div class="wallet-list">
        <div v-for="(w, i) in form.wallets" :key="i" class="wallet-item">
          <div class="wallet-logo" :style="`background: ${getBankColor(w.bank_id)}`">
            {{ getBankInitial(w.bank_id) }}
          </div>
          <div class="wallet-info">
            <div class="wallet-name">{{ w.display_name }}</div>
            <div class="wallet-type">{{ typeLabel(w.type) }}</div>
          </div>
          <button @click="removeWallet(i)" class="remove-btn">✕</button>
        </div>

        <!-- Add wallet button -->
        <button @click="showAddForm = true" v-if="!showAddForm" class="add-wallet-btn">
          <div class="add-icon">＋</div>
          <span>Tambah bank / dompet</span>
        </button>
      </div>

      <!-- Add form -->
      <div v-if="showAddForm" class="add-form">
        <div class="form-group">
          <label class="form-label">Pilih Bank</label>
          <select v-model="newWallet.bank_id" class="form-input-cc" @change="setDisplayName">
            <option value="">💵 Cash / Uang Tunai</option>
            <optgroup v-for="type in bankTypes" :key="type.key" :label="type.label">
              <option v-for="bank in getBanksByType(type.key)" :key="bank.id" :value="bank.id">
                {{ bank.short_name }} — {{ bank.name }}
              </option>
            </optgroup>
          </select>
        </div>

        <div class="form-group">
          <label class="form-label">Nama Tampilan</label>
          <input v-model="newWallet.display_name" type="text" class="form-input-cc"
            placeholder="Contoh: BCA Utama, Mandiri Bisnis" />
        </div>

        <div class="form-group">
          <label class="form-label">Fungsi Rekening</label>
          <select v-model="newWallet.type" class="form-input-cc">
            <option value="both">✅ Keluar-Masuk & Tabungan</option>
            <option value="cash_flow">💸 Keluar-Masuk saja</option>
            <option value="saving">🏦 Tabungan saja</option>
          </select>
        </div>

        <div style="display:flex;gap:8px;">
          <button @click="addWallet" class="btn-cc green" style="flex:1">Tambahkan</button>
          <button @click="showAddForm = false" class="btn-cc" style="flex:1;background:var(--background);color:var(--ink)">Batal</button>
        </div>
      </div>

      <form @submit.prevent="submit" style="margin-top:20px;">
        <button type="submit" class="btn-cc" :disabled="form.processing || form.wallets.length === 0">
          {{ form.processing ? 'Menyimpan...' : 'Lanjut →' }}
        </button>
        <Link :href="route('onboarding.step3')" class="skip-btn">Lewati dulu →</Link>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, reactive } from 'vue'
import { useForm, Link } from '@inertiajs/vue3'

const props = defineProps({ banks: Array })

const showAddForm = ref(false)
const newWallet = reactive({ bank_id: '', display_name: '', type: 'both' })

const form = useForm({ wallets: [] })

const bankTypes = [
  { key: 'conventional', label: 'Bank Konvensional' },
  { key: 'syariah',      label: 'Bank Syariah' },
  { key: 'digital',      label: 'Bank Digital' },
]

const getBanksByType = (type) => props.banks.filter(b => b.type === type)

const getBankColor = (bankId) => {
  if (!bankId) return '#1a7a45'
  return props.banks.find(b => b.id == bankId)?.logo_color ?? '#0F0F0F'
}

const getBankInitial = (bankId) => {
  if (!bankId) return '💵'
  return props.banks.find(b => b.id == bankId)?.logo_initial ?? '?'
}

const setDisplayName = () => {
  if (!newWallet.bank_id) {
    newWallet.display_name = 'Cash'
    return
  }
  const bank = props.banks.find(b => b.id == newWallet.bank_id)
  newWallet.display_name = bank?.short_name ?? ''
}

const typeLabel = (type) => ({
  both: 'Keluar-Masuk & Tabungan',
  cash_flow: 'Keluar-Masuk',
  saving: 'Tabungan',
  investment: 'Investasi',
}[type] ?? type)

const addWallet = () => {
  if (!newWallet.display_name) return
  form.wallets.push({ ...newWallet })
  newWallet.bank_id = ''
  newWallet.display_name = ''
  newWallet.type = 'both'
  showAddForm.value = false
}

const removeWallet = (i) => form.wallets.splice(i, 1)

const submit = () => form.post(route('onboarding.save-step2'))
</script>

<style scoped>
.onboard-page { min-height:100vh;background:var(--background);padding:24px;max-width:480px;margin:0 auto; }
.onboard-progress { margin-bottom:28px; }
.step-label { font-size:11px;font-weight:600;color:var(--text-secondary);letter-spacing:.05em;text-transform:uppercase;margin-bottom:8px; }
.step-bars { display:flex;gap:6px; }
.step-bar { flex:1;height:4px;border-radius:99px;background:var(--border); }
.step-bar.active { background:linear-gradient(135deg, var(--primary) 0%, var(--primary-dark) 100%); }
.onboard-icon { font-size:48px;margin-bottom:16px; }
.onboard-title { font-family:'Plus Jakarta Sans',sans-serif;font-size:24px;font-weight:800;letter-spacing:-.02em;margin-bottom:8px; }
.onboard-sub { font-size:14px;color:var(--text-secondary);line-height:1.6;margin-bottom:20px; }
.wallet-list { display:flex;flex-direction:column;gap:8px;margin-bottom:16px; }
.wallet-item { display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--surface);border-radius:var(--radius-md);border:1.5px solid var(--text-primary);box-shadow:var(--shadow-card); }
.wallet-logo { width:36px;height:36px;border-radius:8px;display:flex;align-items:center;justify-content:center;color:white;font-weight:800;font-family:'Plus Jakarta Sans',sans-serif;font-size:13px;flex-shrink:0; }
.wallet-info { flex:1; }
.wallet-name { font-size:13px;font-weight:600; }
.wallet-type { font-size:11px;color:var(--text-secondary); }
.remove-btn { background:none;border:none;font-size:16px;color:var(--text-secondary);cursor:pointer;padding:4px; }
.add-wallet-btn { display:flex;align-items:center;gap:12px;padding:12px 14px;background:transparent;border:2px dashed var(--border);border-radius:var(--radius-md);cursor:pointer;font-size:13px;font-weight:600;color:var(--text-secondary);transition:all .15s; }
.add-wallet-btn:hover { border-color:var(--text-primary);background:var(--surface); }
.add-icon { width:36px;height:36px;border-radius:8px;background:var(--background);display:flex;align-items:center;justify-content:center;font-size:18px; }
.add-form { background:var(--surface);border-radius:var(--radius-xl);padding:16px;box-shadow:var(--shadow-card);margin-bottom:12px; }
.form-group { margin-bottom:14px; }
.form-label { font-size:12px;font-weight:600;color:var(--text-secondary);display:block;margin-bottom:6px; }
.skip-btn { display:block;text-align:center;margin-top:12px;font-size:13px;color:var(--text-secondary);text-decoration:none; }
</style>
